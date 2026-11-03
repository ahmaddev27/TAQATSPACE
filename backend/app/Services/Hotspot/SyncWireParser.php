<?php

declare(strict_types=1);

namespace App\Services\Hotspot;

use App\Exceptions\SyncWireException;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Converts the plain-text body a router posts (RouterOS 7.12 has no JSON
 * serializer) into the array shape SyncRequest validates. The line layout is
 * documented in DOCS/integration/router-protocol.md and resources/routeros/sync.rsc.
 *
 * Router times arrive as "date time" text on the router's own clock. They are
 * read as UTC so the event times and `now` share one origin, which is all the
 * server-side clock-offset correction needs.
 */
final class SyncWireParser
{
    /** H line fields after the "H" tag, in wire order. */
    public const HEADER_FIELDS = ['batch_id', 'seq', 'boot_id', 'now', 'mode', 'nonce', 'script_version', 'serial', 'routeros_version'];

    /** serial and routeros_version may be left out by older scripts. */
    private const HEADER_REQUIRED_FIELDS = 7;

    private const EVENT_FIELD_COUNT = 10;

    private const SNAPSHOT_ROW_FIELD_COUNT = 6;

    private const TIME_FORMATS = ['!Y-m-d H:i:s', '!M/d/Y H:i:s'];

    /**
     * Reads only the serial of the H line, for the router middleware that must
     * judge the device before the body is validated. Never throws.
     */
    public static function serialOf(string $body): ?string
    {
        $position = array_search('serial', self::HEADER_FIELDS, true) + 1;

        foreach (explode("\n", str_replace("\r", '', $body)) as $line) {
            if (str_starts_with($line, 'H|')) {
                $serial = trim(explode('|', $line)[$position] ?? '');

                return $serial === '' ? null : $serial;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws SyncWireException
     */
    public function parse(string $body): array
    {
        $result = ['events' => [], 'snapshot' => null, 'acks' => []];
        $rows = null;

        foreach (explode("\n", str_replace("\r", '', $body)) as $index => $line) {
            if ($line === '') {
                continue;
            }

            $fields = explode('|', $line);

            // A snapshot row starts with a user name, which may spell "A" or "E",
            // so inside a chunk the field count is what tells a row from a section line.
            if ($rows !== null && count($fields) === self::SNAPSHOT_ROW_FIELD_COUNT) {
                $rows[] = $line;

                continue;
            }

            $this->closeSnapshot($result, $rows);
            $this->readLine($result, $fields, $index + 1);
            $rows = $fields[0] === 'S' ? [] : null;
        }

        $this->closeSnapshot($result, $rows);

        if (! isset($result['batch_id'])) {
            throw SyncWireException::atLine(1, 'the H line is missing');
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  list<string>  $fields
     */
    private function readLine(array &$result, array $fields, int $line): void
    {
        match ($fields[0]) {
            'H' => $this->readHeader($result, $fields, $line),
            'E' => $result['events'][] = $this->readEvent($fields, $line),
            'S' => $result['snapshot'] = $this->readSnapshotHead($result, $fields, $line),
            'A' => $result['acks'][] = $this->readAck($fields, $line),
            default => throw SyncWireException::atLine($line, 'unknown line type'),
        };
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  list<string>|null  $rows
     */
    private function closeSnapshot(array &$result, ?array $rows): void
    {
        if ($rows !== null) {
            $result['snapshot']['data'] = implode("\n", $rows);
        }
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  list<string>  $fields
     */
    private function readHeader(array &$result, array $fields, int $line): void
    {
        if (isset($result['batch_id'])) {
            throw SyncWireException::atLine($line, 'duplicate H line');
        }

        $width = count(self::HEADER_FIELDS);

        if (count($fields) - 1 < self::HEADER_REQUIRED_FIELDS || count($fields) - 1 > $width) {
            throw SyncWireException::atLine($line, 'the H line has the wrong number of fields');
        }

        $values = array_slice(array_pad($fields, $width + 1, ''), 1);

        foreach (self::HEADER_FIELDS as $position => $name) {
            $result[$name] = $this->nullable($values[$position]);
        }

        $result['now'] = $this->epoch((string) $result['now'], $line);
    }

    /**
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    private function readEvent(array $fields, int $line): array
    {
        if (count($fields) !== self::EVENT_FIELD_COUNT) {
            throw SyncWireException::atLine($line, 'an E line needs 10 fields');
        }

        [, $seq, $type, $at, $username, $mac, $ip, $uptime, $bytesIn, $bytesOut] = $fields;

        return [
            'seq' => $seq,
            'type' => $type,
            'at' => $this->epoch($at, $line),
            'username' => $this->nullable($username),
            'mac' => $this->nullable($mac),
            'ip' => $this->nullable($ip),
            'data' => $this->eventData($uptime, $bytesIn, $bytesOut),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function eventData(string $uptime, string $bytesIn, string $bytesOut): ?array
    {
        $data = array_filter([
            'uptime' => $this->nullable($uptime),
            'bytes_in' => ctype_digit($bytesIn) ? (int) $bytesIn : null,
            'bytes_out' => ctype_digit($bytesOut) ? (int) $bytesOut : null,
        ], static fn (mixed $value): bool => $value !== null);

        return $data === [] ? null : $data;
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    private function readSnapshotHead(array $result, array $fields, int $line): array
    {
        if ($result['snapshot'] !== null) {
            throw SyncWireException::atLine($line, 'only one S chunk is allowed per batch');
        }

        if (count($fields) !== 4 || ! isset($result['now'])) {
            throw SyncWireException::atLine($line, 'an S line needs 3 fields and must follow the H line');
        }

        return ['id' => $fields[1], 'index' => $fields[2], 'total' => $fields[3], 'taken_at' => $result['now'], 'data' => ''];
    }

    /**
     * @param  list<string>  $fields
     * @return array{command_id: string, status: string}
     */
    private function readAck(array $fields, int $line): array
    {
        if (count($fields) !== 3 || $fields[1] === '' || $fields[2] === '') {
            throw SyncWireException::atLine($line, 'an A line needs a command id and a status');
        }

        return ['command_id' => $fields[1], 'status' => $fields[2]];
    }

    private function epoch(string $text, int $line): int
    {
        $normalised = ucfirst(strtolower(trim($text)));

        foreach (self::TIME_FORMATS as $format) {
            $parsed = DateTimeImmutable::createFromFormat($format, $normalised, new DateTimeZone('UTC'));
            $errors = DateTimeImmutable::getLastErrors();

            if ($parsed !== false && ($errors === false || $errors['warning_count'] + $errors['error_count'] === 0)) {
                return $parsed->getTimestamp();
            }
        }

        throw SyncWireException::atLine($line, 'unreadable date and time');
    }

    private function nullable(string $value): ?string
    {
        return $value === '' ? null : $value;
    }
}
