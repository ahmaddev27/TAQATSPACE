<?php

declare(strict_types=1);

namespace App\Testing\Hotspot;

use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * A virtual MikroTik router that follows resources/routeros/sync.rsc: the
 * event counter survives reboots (flash state file), the boot id does not, a
 * failed send keeps the same batch (same batch_id, seq, nonce, events, snapshot
 * chunk and acks) while the H line is rebuilt with the current clock at every
 * attempt, and the reply is read by the interpreter twin, which queues `A|`
 * acknowledgements. The wire text is built field by field from
 * DOCS/integration/router-protocol.md.
 *
 * A batch holds at most MAX_EVENTS_PER_BATCH events, the rest stays buffered in
 * order. A batch the server keeps refusing is dropped after
 * QUARANTINE_AFTER_FAILURES failed sends, but only when a header-only probe
 * proves the server is reachable, exactly like sync.rsc.
 */
final class RouterSimulator
{
    public const SCRIPT_VERSION = '1.1.0';

    public const MAX_EVENTS_PER_BATCH = 500;

    private const SNAPSHOT_ROWS_PER_CHUNK = 100;

    private const SNAPSHOT_EVERY_CYCLES = 36;

    public const QUARANTINE_AFTER_FAILURES = 5;

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

    /** @var array{batchId: string, seq: int, nonce: string, payload: string}|null the batch kept across failed sends, without its H line */
    private ?array $pending = null;

    private int $failures = 0;

    private int $droppedBatches = 0;

    /** @var list<string> uids of the events inside dropped batches */
    private array $droppedUids = [];

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

    public function droppedBatches(): int
    {
        return $this->droppedBatches;
    }

    /** @return list<string> */
    public function droppedEventUids(): array
    {
        return $this->droppedUids;
    }

    public function isActive(SimulatedDevice $device): bool
    {
        return isset($this->active[$device->mac]);
    }

    /** True while anything the router still has to deliver is waiting. */
    public function hasPending(): bool
    {
        return $this->events !== [] || $this->pending !== null || $this->snapshotChunks !== [] || $this->acks !== '';
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
        $this->pending = null;
        $this->failures = 0;
        $this->active = [];
        $this->cycles = 0;
    }

    public function tick(RouterTransport $transport, int $now): TickResult
    {
        $this->pending ??= $this->freezeBatch();
        $pending = $this->pending;
        $eventCount = $this->countEvents($pending['payload']);
        $body = $this->head($pending['batchId'], $pending['seq'], $pending['nonce'], $now).$pending['payload'];

        try {
            $response = $transport->post($this->code, $this->apiKey, $body);
        } catch (TransportException) {
            return new TickResult(null, false);
        }

        if (! $response->successful()) {
            $this->onRefused($transport, $now);

            return new TickResult($response->status, false);
        }

        $this->deliveredBody = $body;
        $this->persistedSeq = $this->seq;
        $this->pending = null;
        $this->failures = 0;
        $this->interpret($response->body, $pending['nonce']);

        return new TickResult($response->status, true, $eventCount);
    }

    /** Counts a failed send and, once the batch keeps being refused while the server answers, drops it. */
    private function onRefused(RouterTransport $transport, int $now): void
    {
        if (++$this->failures < self::QUARANTINE_AFTER_FAILURES || $this->pending === null) {
            return;
        }

        $nonce = $this->randomString(12, 'abcdefghijklmnopqrstuvwxyz0123456789');
        $probe = $this->head($this->randomString(12, 'abcdef0123456789'), $this->seq, $nonce, $now);

        try {
            $response = $transport->post($this->code, $this->apiKey, $probe);
        } catch (TransportException) {
            return;
        }

        if (! $response->successful()) {
            return;
        }

        $this->droppedBatches++;
        $this->droppedUids = [...$this->droppedUids, ...$this->pendingEventUids($this->pending['payload'])];
        $this->pending = null;
        $this->failures = 0;
        $this->persistedSeq = $this->seq;
        $this->interpret($response->body, $nonce);
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

        return new TickResult($response->status, $response->successful(), $this->countEvents($body));
    }

    /** @return array{batchId: string, seq: int, nonce: string, payload: string} */
    private function freezeBatch(): array
    {
        $this->cycles++;
        $chunk = $this->nextSnapshotChunk();
        $batch = array_splice($this->events, 0, self::MAX_EVENTS_PER_BATCH);
        $payload = implode('', array_map(static fn (array $event): string => $event['line']."\n", $batch)).$chunk.$this->acks;
        $this->acks = '';

        return [
            'batchId' => $this->randomString(12, 'abcdef0123456789'),
            'seq' => $this->seq,
            'nonce' => $this->randomString(12, 'abcdefghijklmnopqrstuvwxyz0123456789'),
            'payload' => $payload,
        ];
    }

    /** The H line is rebuilt for every attempt so `now` is always the current clock. */
    private function head(string $batchId, int $seq, string $nonce, int $now): string
    {
        return implode('|', ['H', $batchId, $seq, $this->bootId, $this->routerTime($now), $this->mode, $nonce, self::SCRIPT_VERSION, sprintf('SIM%08d', $this->index), '7.12.1'])."\n";
    }

    private function countEvents(string $payload): int
    {
        return (int) preg_match_all('/^E\|/m', $payload);
    }

    /** @return list<string> */
    private function pendingEventUids(string $payload): array
    {
        preg_match_all('/^E\|(\d+)\|/m', $payload, $matches);

        return array_map(fn (string $seq): string => $this->bootId.'-'.$seq, $matches[1]);
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
