<?php

declare(strict_types=1);

namespace App\Services\Network;

use App\Support\Router\RouterOsDuration;

/**
 * One device line of a reassembled snapshot: "user|mac|ip|uptime|bytes-in|bytes-out".
 */
final readonly class SnapshotRow
{
    private const FIELD_COUNT = 6;

    public function __construct(
        public string $mac,
        public ?string $username,
        public ?string $ip,
        public int $uptimeSeconds,
        public int $bytesIn,
        public int $bytesOut,
    ) {}

    /** Returns null for a line that is not a device row or has no MAC. */
    public static function fromLine(string $line): ?self
    {
        $fields = explode('|', trim($line));

        if (count($fields) !== self::FIELD_COUNT) {
            return null;
        }

        [$username, $mac, $ip, $uptime, $bytesIn, $bytesOut] = $fields;
        $mac = strtoupper(trim($mac));

        if ($mac === '') {
            return null;
        }

        return new self(
            mac: $mac,
            username: $username === '' ? null : $username,
            ip: $ip === '' ? null : $ip,
            uptimeSeconds: RouterOsDuration::toSeconds($uptime),
            bytesIn: ctype_digit($bytesIn) ? (int) $bytesIn : 0,
            bytesOut: ctype_digit($bytesOut) ? (int) $bytesOut : 0,
        );
    }
}
