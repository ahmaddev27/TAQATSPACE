<?php

declare(strict_types=1);

namespace Tests\Unit\Hotspot;

use App\Testing\Hotspot\RouterSimulator;
use App\Testing\Hotspot\RouterTransport;
use App\Testing\Hotspot\SimulatedDevice;
use App\Testing\Hotspot\SimulationDataset;
use App\Testing\Hotspot\TransportException;
use App\Testing\Hotspot\TransportResponse;
use PHPUnit\Framework\TestCase;

class RouterSimulatorTest extends TestCase
{
    private const NOW = 1_793_707_200; // 2026-11-03 12:00:00 UTC

    private function device(int $n = 1): SimulatedDevice
    {
        return new SimulatedDevice('sub0000'.$n, sprintf('02:00:00:00:00:%02X', $n), "10.0.0.{$n}", 0);
    }

    private function simulator(int $skew = 0, bool $legacy = false, string $mode = 'full'): RouterSimulator
    {
        return new RouterSimulator('ABCD2345', 'key', 0, 1, $skew, $legacy, $mode);
    }

    /** @param  list<TransportResponse|TransportException>  $replies */
    private function transport(array $replies, array &$bodies = []): RouterTransport
    {
        return new class($replies, $bodies) implements RouterTransport
        {
            public function __construct(private array $replies, private array &$bodies) {}

            public function post(string $routerCode, string $apiKey, string $body): TransportResponse
            {
                $this->bodies[] = $body;
                $reply = array_shift($this->replies) ?? new TransportResponse(200, 'CWS-SYNC x'."\n");

                return $reply instanceof TransportException ? throw $reply : $reply;
            }
        };
    }

    /** Echoes the nonce the way the server does. */
    private function echoing(string ...$commandLines): RouterTransport
    {
        return new class($commandLines) implements RouterTransport
        {
            public function __construct(private array $lines) {}

            public function post(string $routerCode, string $apiKey, string $body): TransportResponse
            {
                $nonce = explode('|', explode("\n", $body)[0])[6];
                $lines = $this->lines;
                $this->lines = [];

                return new TransportResponse(200, "CWS-SYNC {$nonce}\n".implode('', array_map(static fn (string $l): string => $l."\n", $lines)));
            }
        };
    }

    public function test_batch_follows_the_documented_wire_format(): void
    {
        $sim = $this->simulator();
        $sim->record('login', $this->device(), self::NOW);
        $bodies = [];

        $sim->tick($this->transport([], $bodies), self::NOW);

        $lines = explode("\n", rtrim($bodies[0]));
        $head = explode('|', $lines[0]);
        $this->assertSame('H', $head[0]);
        $this->assertCount(10, $head);
        $this->assertSame('1', $head[2]);
        $this->assertSame('2026-11-03 12:00:00', $head[4]);
        $this->assertSame('full', $head[5]);
        $this->assertMatchesRegularExpression('/^[a-z0-9]{12}$/', $head[6]);
        $this->assertSame('E|1|login|2026-11-03 12:00:00|sub00001|02:00:00:00:00:01|10.0.0.1|0s|0|0', $lines[1]);
        $this->assertStringStartsWith('S|', $lines[2]);
    }

    public function test_seq_keeps_counting_and_never_repeats(): void
    {
        $sim = $this->simulator();

        foreach ([1, 2, 3] as $n) {
            $sim->record('login', $this->device($n), self::NOW);
        }

        $this->assertSame(3, $sim->seq());
        $this->assertSame(['%s-1', '%s-2', '%s-3'], array_map(fn (string $uid): string => str_replace($sim->bootId(), '%s', $uid), array_keys($sim->generatedEvents())));
    }

    public function test_a_failed_send_keeps_the_same_batch_and_adds_new_events_to_the_next_one(): void
    {
        $sim = $this->simulator();
        $sim->record('login', $this->device(1), self::NOW);
        $bodies = [];
        $transport = $this->transport([new TransportException('down'), new TransportResponse(500, 'oops'), new TransportResponse(200, "CWS-SYNC x\n")], $bodies);

        $this->assertNull($sim->tick($transport, self::NOW)->status);
        $sim->record('login', $this->device(2), self::NOW + 50);
        $this->assertSame(500, $sim->tick($transport, self::NOW + 50)->status);
        $delivered = $sim->tick($transport, self::NOW + 100);

        $this->assertTrue($delivered->delivered);
        $this->assertSame($bodies[0], $bodies[1]);
        $this->assertSame($bodies[0], $bodies[2]);
        $this->assertSame(1, substr_count($bodies[2], "\nE|"));
        $this->assertTrue($sim->hasPending());

        $sim->tick($transport, self::NOW + 150);
        $this->assertSame(1, substr_count($bodies[3], "\nE|"));
        $this->assertStringContainsString('|10.0.0.2|', $bodies[3]);
    }

    public function test_at_most_500_events_go_into_one_batch(): void
    {
        $sim = $this->simulator();

        for ($i = 0; $i < 620; $i++) {
            $sim->record('login', $this->device(1 + $i % 200), self::NOW);
        }

        $bodies = [];
        $transport = $this->transport([], $bodies);
        $sim->tick($transport, self::NOW);
        $sim->tick($transport, self::NOW + 50);

        $this->assertSame(500, substr_count($bodies[0], "\nE|"));
        $this->assertSame(120, substr_count($bodies[1], "\nE|"));
    }

    public function test_clock_skew_shifts_every_router_time_and_the_legacy_format_is_supported(): void
    {
        $sim = $this->simulator(3600, true);
        $sim->record('login', $this->device(), self::NOW);
        $bodies = [];

        $sim->tick($this->transport([], $bodies), self::NOW);

        $this->assertStringContainsString('|nov/03/2026 13:00:00|', $bodies[0]);
        $this->assertStringContainsString('|login|nov/03/2026 13:00:00|', $bodies[0]);
    }

    public function test_commands_are_run_by_the_interpreter_and_acknowledged_in_the_next_batch(): void
    {
        $sim = $this->simulator();
        $bodies = [];
        $echo = $this->echoing('C|id-1|upsert-user|guest1|secret|', 'C|id-2|block-mac|aa:bb|', 'C|id-3|format-disk|x', 'C|id-4|upsert-profile|gold|2M/2M|');

        $sim->tick($echo, self::NOW);
        $sim->tick($this->transport([], $bodies), self::NOW + 50);

        $this->assertStringContainsString("A|id-1|ok\nA|id-2|bad-args\nA|id-3|not-allowed\nA|id-4|ok\n", $bodies[0]);
        $this->assertSame('visitor', $sim->interpreter()->users['guest1']['profile']);
        $this->assertSame(['gold' => '2M/2M'], $sim->interpreter()->profiles);
    }

    public function test_a_reply_with_the_wrong_nonce_is_dropped(): void
    {
        $sim = $this->simulator();

        $sim->tick($this->transport([new TransportResponse(200, "CWS-SYNC wrong\nC|id-1|upsert-user|guest1|secret|\n")]), self::NOW);

        $this->assertSame([], $sim->interpreter()->users);
        $this->assertFalse($sim->hasPending());
    }

    public function test_poll_only_mode_rejects_hotspot_verbs_and_records_no_events(): void
    {
        $sim = $this->simulator(mode: 'poll-only');
        $sim->record('login', $this->device(), self::NOW);

        $sim->tick($this->echoing('C|id-1|upsert-user|guest1|secret|'), self::NOW);
        $bodies = [];
        $sim->tick($this->transport([], $bodies), self::NOW + 50);

        $this->assertSame(0, $sim->seq());
        $this->assertStringContainsString('A|id-1|poll-only', $bodies[0]);
    }

    public function test_reboot_keeps_the_persisted_seq_changes_the_boot_id_and_drops_unsent_events(): void
    {
        $sim = $this->simulator();
        $sim->record('login', $this->device(1), self::NOW);
        $sim->tick($this->transport([]), self::NOW);
        $before = $sim->bootId();
        $sim->record('login', $this->device(2), self::NOW + 50);

        $sim->reboot();

        $this->assertNotSame($before, $sim->bootId());
        $this->assertSame(1, $sim->seq());
        $this->assertFalse($sim->hasPending());
    }

    public function test_a_resent_batch_keeps_its_stale_now_unless_the_router_refreshes_it(): void
    {
        foreach ([false, true] as $refresh) {
            $sim = new RouterSimulator('ABCD2345', 'key', 0, 1, refreshNowOnResend: $refresh);
            $sim->record('login', $this->device(), self::NOW);
            $bodies = [];
            $transport = $this->transport([new TransportException('down')], $bodies);

            $sim->tick($transport, self::NOW);
            $sim->tick($transport, self::NOW + 3600);

            $expected = $refresh ? '2026-11-03 13:00:00' : '2026-11-03 12:00:00';
            $this->assertSame($expected, explode('|', explode('
', $bodies[1])[0])[4]);
            $this->assertSame(explode('|', explode('
', $bodies[0])[0])[1], explode('|', explode('
', $bodies[1])[0])[1], 'batch id must not change');
        }
    }

    public function test_replay_sends_the_same_events_again(): void
    {
        $sim = $this->simulator();
        $sim->record('login', $this->device(), self::NOW);
        $bodies = [];
        $transport = $this->transport([], $bodies);
        $sim->tick($transport, self::NOW);

        $sim->replay($transport, false);
        $sim->replay($transport, true);

        $this->assertSame($bodies[0], $bodies[1]);
        $this->assertNotSame(explode("\n", $bodies[0])[0], explode("\n", $bodies[2])[0]);
        $this->assertSame(array_slice(explode("\n", $bodies[0]), 1), array_slice(explode("\n", $bodies[2]), 1));
    }

    public function test_dataset_is_deterministic_and_assigns_every_device(): void
    {
        $first = SimulationDataset::generate(7, 461, 1149, 3);

        $this->assertEquals($first, SimulationDataset::generate(7, 461, 1149, 3));
        $this->assertCount(1149, $first);
        $this->assertCount(1149, array_unique(array_map(static fn (SimulatedDevice $d): string => $d->mac, $first)));
        $this->assertCount(461, array_unique(array_map(static fn (SimulatedDevice $d): string => $d->username, $first)));
    }
}
