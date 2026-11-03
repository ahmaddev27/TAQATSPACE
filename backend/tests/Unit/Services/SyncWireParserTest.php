<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\SyncWireException;
use App\Services\Hotspot\SyncWireParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SyncWireParserTest extends TestCase
{
    private const HEADER = 'H|batch1|7|boot9|2026-11-03 10:00:00|full|nonce77|1.0.0|SN123|7.14.3 (stable)';

    private function parse(string ...$lines): array
    {
        return (new SyncWireParser)->parse(implode("\n", $lines)."\n");
    }

    public function test_header_maps_every_field_including_nonce_mode_serial_and_version(): void
    {
        $result = $this->parse(self::HEADER);

        $this->assertSame('batch1', $result['batch_id']);
        $this->assertSame('7', $result['seq']);
        $this->assertSame('boot9', $result['boot_id']);
        $this->assertSame(strtotime('2026-11-03 10:00:00 UTC'), $result['now']);
        $this->assertSame('full', $result['mode']);
        $this->assertSame('nonce77', $result['nonce']);
        $this->assertSame('1.0.0', $result['script_version']);
        $this->assertSame('SN123', $result['serial']);
        $this->assertSame('7.14.3 (stable)', $result['routeros_version']);
        $this->assertSame([], $result['events']);
        $this->assertNull($result['snapshot']);
        $this->assertSame([], $result['acks']);
    }

    public function test_header_without_serial_and_version_is_accepted_and_empty_fields_are_null(): void
    {
        $result = $this->parse('H|b|1|boot|nov/03/2026 10:00:00|poll-only|n1|1.0.0');

        $this->assertNull($result['serial']);
        $this->assertSame(strtotime('2026-11-03 10:00:00 UTC'), $result['now']);

        $this->assertNull($this->parse('H|b|1|boot|2026-11-03 10:00:00|full||1.0.0||')['nonce']);
    }

    public function test_event_lines_become_events_with_epoch_times_and_data(): void
    {
        $result = $this->parse(
            self::HEADER,
            'E|1|login|2026-11-03 09:59:00|u1|AA:BB:CC:DD:EE:01|10.0.0.5|||',
            'E|2|logout|nov/03/2026 09:59:30|u1|AA:BB:CC:DD:EE:01|10.0.0.5|1h2m3s|1000|2000',
        );

        $this->assertSame([
            'seq' => '1', 'type' => 'login', 'at' => strtotime('2026-11-03 09:59:00 UTC'),
            'username' => 'u1', 'mac' => 'AA:BB:CC:DD:EE:01', 'ip' => '10.0.0.5', 'data' => null,
        ], $result['events'][0]);
        $this->assertSame(strtotime('2026-11-03 09:59:30 UTC'), $result['events'][1]['at']);
        $this->assertSame(['uptime' => '1h2m3s', 'bytes_in' => 1000, 'bytes_out' => 2000], $result['events'][1]['data']);
    }

    public function test_snapshot_rows_follow_the_s_line_and_acks_follow_the_rows(): void
    {
        $result = $this->parse(
            self::HEADER,
            'S|snap1|0|2',
            'alice|AA:BB:CC:DD:EE:01|10.0.0.5|1h|10|20',
            'A|AA:BB:CC:DD:EE:02|10.0.0.6|2h|30|40',
            'A|cmd1|ok',
            'A|cmd2|bad-args',
        );

        $this->assertSame('snap1', $result['snapshot']['id']);
        $this->assertSame('0', $result['snapshot']['index']);
        $this->assertSame('2', $result['snapshot']['total']);
        $this->assertSame($result['now'], $result['snapshot']['taken_at']);
        $this->assertSame("alice|AA:BB:CC:DD:EE:01|10.0.0.5|1h|10|20\nA|AA:BB:CC:DD:EE:02|10.0.0.6|2h|30|40", $result['snapshot']['data']);
        $this->assertSame([
            ['command_id' => 'cmd1', 'status' => 'ok'],
            ['command_id' => 'cmd2', 'status' => 'bad-args'],
        ], $result['acks']);
    }

    public function test_an_s_line_without_rows_is_an_empty_chunk(): void
    {
        $this->assertSame('', $this->parse(self::HEADER, 'S|snap1|0|1')['snapshot']['data']);
    }

    public function test_events_after_a_snapshot_are_still_read_and_crlf_is_tolerated(): void
    {
        $body = self::HEADER."\r\nS|s|0|1\r\nu|m|i|1|2|3\r\nE|1|login|2026-11-03 09:59:00|u|||||\r\n";

        $result = (new SyncWireParser)->parse($body);

        $this->assertSame('u|m|i|1|2|3', $result['snapshot']['data']);
        $this->assertCount(1, $result['events']);
    }

    #[DataProvider('malformedBodies')]
    public function test_malformed_input_is_rejected(string $body): void
    {
        $this->expectException(SyncWireException::class);

        (new SyncWireParser)->parse($body);
    }

    /** @return array<string, array{string}> */
    public static function malformedBodies(): array
    {
        return [
            'empty body' => [''],
            'no header' => ['E|1|login|2026-11-03 09:59:00|u|||||'],
            'unknown line type' => [self::HEADER."\nX|1|2"],
            'duplicate header' => [self::HEADER."\n".self::HEADER],
            'short header' => ['H|b|1|boot|2026-11-03 10:00:00|full'],
            'long header' => [self::HEADER.'|extra'],
            'unreadable time' => ['H|b|1|boot|yesterday|full|n|1.0.0'],
            'impossible date' => ['H|b|1|boot|2026-02-31 10:00:00|full|n|1.0.0'],
            'short event' => [self::HEADER."\nE|1|login|2026-11-03 09:59:00|u"],
            'event with bad time' => [self::HEADER."\nE|1|login|soon|u|||||"],
            'short snapshot head' => [self::HEADER."\nS|snap|0"],
            'two snapshot chunks' => [self::HEADER."\nS|a|0|1\nS|b|0|1"],
            'ack without status' => [self::HEADER."\nA|cmd1|"],
            'ack with extra field' => [self::HEADER."\nA|cmd1|ok|x"],
        ];
    }
}
