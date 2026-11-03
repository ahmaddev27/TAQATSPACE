<?php

declare(strict_types=1);

namespace App\Testing\Hotspot;

use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * A virtual MikroTik router that follows resources/routeros/sync.rsc: the
 * event counter survives reboots (flash state file), the boot id does not, a
 * failed send keeps the exact same batch (same batch_id, same events), and the
 * reply is read by the interpreter twin, which queues `A|` acknowledgements.
 * The wire text is built field by field from DOCS/integration/router-protocol.md.
 *
 * sync.rsc freezes the whole batch, including the `now` in its H line, so after
 * an outage the resent batch carries a stale `now` and the server shifts every
 * event by the outage length. `$refreshNowOnResend` models a router that
 * rewrites `now` on resend; it is off by default to stay faithful to the script.
 *
 * One deliberate difference: sync.rsc never splits its event buffer, so a long
 * outage would exceed the server's 500-event limit; the simulator sends at
 * most that many events per batch and keeps the rest for the next cycle.
 */
final class RouterSimulator
{
    public const SCRIPT_VERSION = '1.0.0';

    public const MAX_EVENTS_PER_BATCH = 500;

    private const SNAPSHOT_ROWS_PER_CHUNK = 100;

    private const SNAPSHOT_EVERY_CYCLES = 36;

    private int $seq = 0;

    private int $persistedSeq = 0;

    private int $bootCount = 0;

    private int $cycles = 0;

    private string $bootId;

    private Randomizer $random;

    private RouterInterpreter $interpreter;

    /** @var list<array{seq: int, line: string}> events recorded by the hooks and not yet batched */
    private array $events = [];

    /** @var list<string> snapshot chunks not yet sent, each a full `S` section */
    private array $snapshotChunks = [];

    private string $acks = '';

    /** The frozen batch kept across failed sends. */
    private ?string $pendingBody = null;

    private ?string $pendingNonce = null;

    private ?string $deliveredBody = null;

    /** @var array<string, SimulatedDevice> mac => device, the hotspot "active" table */
    private array $active = [];

    /** @var array<string, int> event uid => virtual time the event really happened */
    private array $generated = [];

    public function __construct(
        public readonly string $code,
        private string $apiKey,
        public readonly int $index,
        int $seed,
        private int $clockSkewSeconds = 0,
        private readonly bool $legacyDateFormat = false,
        private readonly string $mode = 'full',
        private readonly bool $refreshNowOnResend = false,
    ) {
        $this->random = new Randomizer(new Mt19937($seed + $index));
        $this->interpreter = new RouterInterpreter($mode);
        $this->bootId = $this->newBootId();
    }

    public function interpreter(): RouterInterpreter
    {
        return $this->interpreter;
    }

    public function bootId(): string
    {
        return $this->bootId;
    }

    public function seq(): int
    {
        return $this->seq;
    }

    public function apiKey(): string
    {
        return $this->apiKey;
    }

    /** @return array<string, int> */
    public function generatedEvents(): array
    {
        return $this->generated;
    }

    public function isActive(SimulatedDevice $device): bool
    {
        return isset($this->active[$device->mac]);
    }

    /** True while anything the router still has to deliver is waiting. */
    public function hasPending(): bool
    {
        return $this->events !== [] || $this->pendingBody !== null || $this->snapshotChunks !== [] || $this->acks !== '';
    }

    public function setClockSkew(int $seconds): void
    {
        $this->clockSkewSeconds = $seconds;
    }

    /** Hook for a hotspot login or logout; poll-only routers have no hooks installed. */
    public function record(string $type, SimulatedDevice $device, int $now): void
    {
        if ($type === 'login') {
            $this->active[$device->mac] = $device;
        } else {
            unset($this->active[$device->mac]);
        }

        if ($this->mode === 'poll-only') {
            return;
        }

        $seq = ++$this->seq;
        $this->generated[$this->bootId.'-'.$seq] = $now;
        $this->events[] = ['seq' => $seq, 'line' => $this->eventLine($seq, $type, $device, $now)];
    }

    /** A power cycle: RAM (unsent events, frozen batch, acks, sessions) is lost, the flash state is not. */
    public function reboot(): void
    {
        $this->bootCount++;
        $this->bootId = $this->newBootId();
        $this->seq = $this->persistedSeq;
        $this->events = [];
        $this->snapshotChunks = [];
        $this->acks = '';
        $this->pendingBody = null;
        $this->pendingNonce = null;
        $this->active = [];
        $this->cycles = 0;
    }

    public function tick(RouterTransport $transport, int $now): TickResult
    {
        if ($this->pendingBody === null) {
            $this->freezeBatch($now);
        } elseif ($this->refreshNowOnResend) {
            $this->pendingBody = $this->withHeaderField($this->pendingBody, 4, $this->routerTime($now));
        }

        $eventCount = substr_count((string) $this->pendingBody, "\nE|");

        try {
            $response = $transport->post($this->code, $this->apiKey, (string) $this->pendingBody);
        } catch (TransportException) {
            return new TickResult(null, false);
        }

        if (! $response->successful()) {
            return new TickResult($response->status, false);
        }

        $this->deliveredBody = $this->pendingBody;
        $this->persistedSeq = $this->seq;
        $nonce = (string) $this->pendingNonce;
        $this->pendingBody = null;
        $this->interpret($response->body, $nonce);

        return new TickResult($response->status, true, $eventCount);
    }

    /**
     * Posts the last delivered batch again, as a router does when the reply was lost.
     * With $newBatchId the events and acknowledgements are replayed under a fresh
     * batch id, which bypasses the reply cache and exercises the per-event dedupe.
     */
    public function replay(RouterTransport $transport, bool $newBatchId): ?TickResult
    {
        if ($this->deliveredBody === null) {
            return null;
        }

        $body = $newBatchId ? $this->withFreshBatchId($this->deliveredBody) : $this->deliveredBody;

        try {
            $response = $transport->post($this->code, $this->apiKey, $body);
        } catch (TransportException) {
            return new TickResult(null, false);
        }

        return new TickResult($response->status, $response->successful(), substr_count($body, "\nE|"));
    }

    private function freezeBatch(int $now): void
    {
        $this->cycles++;
        $chunk = $this->nextSnapshotChunk();
        $nonce = $this->randomString(12, 'abcdefghijklmnopqrstuvwxyz0123456789');
        $batch = array_splice($this->events, 0, self::MAX_EVENTS_PER_BATCH);

        $head = implode('|', ['H', $this->randomString(12, 'abcdef0123456789'), $this->seq, $this->bootId, $this->routerTime($now), $this->mode, $nonce, self::SCRIPT_VERSION, sprintf('SIM%08d', $this->index), '7.12.1'])."\n";

        $this->pendingBody = $head.implode('', array_map(static fn (array $event): string => $event['line']."\n", $batch)).$chunk.$this->acks;
        $this->pendingNonce = $nonce;
        $this->acks = '';
    }

    /** One chunk per cycle, like cwsNextSnapshotChunk; a new snapshot starts on the first cycle and then on cadence. */
    private function nextSnapshotChunk(): string
    {
        $every = $this->mode === 'poll-only' ? 1 : self::SNAPSHOT_EVERY_CYCLES;

        if ($this->snapshotChunks === [] && ($this->cycles - 1) % $every === 0) {
            $this->snapshotChunks = $this->buildSnapshot();
        }

        return array_shift($this->snapshotChunks) ?? '';
    }

    /** @return list<string> */
    private function buildSnapshot(): array
    {
        $rows = array_map(
            static fn (SimulatedDevice $device): string => implode('|', [$device->username, $device->mac, $device->ip, '1m', '1000', '2000'])."\n",
            array_values($this->active),
        );
        $groups = $rows === [] ? [[]] : array_chunk($rows, self::SNAPSHOT_ROWS_PER_CHUNK);
        $snapshotId = sprintf('snap-%s-%d', $this->bootId, $this->cycles);

        return array_map(
            static fn (array $group, int $position): string => implode('|', ['S', $snapshotId, $position, count($groups)])."\n".implode('', $group),
            $groups,
            array_keys($groups),
        );
    }

    private function eventLine(int $seq, string $type, SimulatedDevice $device, int $now): string
    {
        $bytes = $type === 'login' ? ['0s', '0', '0'] : ['5m', (string) $this->random->getInt(1000, 90000), (string) $this->random->getInt(1000, 900000)];

        return implode('|', ['E', $seq, $type, $this->routerTime($now), $device->username, $device->mac, $device->ip, ...$bytes]);
    }

    /** The router's own clock in its `date time` text; older RouterOS prints `nov/03/2026`. */
    private function routerTime(int $now): string
    {
        $at = $now + $this->clockSkewSeconds;

        return $this->legacyDateFormat ? strtolower(gmdate('M/d/Y H:i:s', $at)) : gmdate('Y-m-d H:i:s', $at);
    }

    /** Mirrors cwsInterpret: the whole reply is dropped unless it echoes our nonce. */
    private function interpret(string $response, string $nonce): void
    {
        $lines = explode("\n", $response);
        $header = explode(' ', $lines[0]);

        if (($header[0] ?? '') !== 'CWS-SYNC' || ($header[1] ?? '') !== $nonce) {
            return;
        }

        foreach ($lines as $line) {
            if (! str_starts_with($line, 'C|')) {
                continue;
            }

            $fields = explode('|', $line);
            $this->acks .= 'A|'.($fields[1] ?? '').'|'.$this->interpreter->run($fields)."\n";
        }

        $this->apiKey = $this->interpreter->newKey ?? $this->apiKey;
    }

    private function withFreshBatchId(string $body): string
    {
        $fresh = $this->withHeaderField($body, 1, $this->randomString(12, 'abcdef0123456789'));

        return $this->withHeaderField($fresh, 6, $this->randomString(12, 'abcdefghijklmnopqrstuvwxyz0123456789'));
    }

    private function withHeaderField(string $body, int $position, string $value): string
    {
        $lines = explode("\n", $body);
        $head = explode('|', $lines[0]);
        $head[$position] = $value;
        $lines[0] = implode('|', $head);

        return implode("\n", $lines);
    }

    private function newBootId(): string
    {
        return sprintf('b%s%d', $this->randomString(6, 'abcdef0123456789'), $this->bootCount);
    }

    private function randomString(int $length, string $alphabet): string
    {
        return $this->random->getBytesFromString($alphabet, $length);
    }
}
