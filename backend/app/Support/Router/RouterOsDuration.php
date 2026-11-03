<?php

declare(strict_types=1);

namespace App\Support\Router;

/**
 * Converts the duration text RouterOS prints ("1w2d3h4m5s" or "01:02:03")
 * into whole seconds. Unreadable text counts as zero so one odd row never
 * rejects a whole snapshot.
 */
final class RouterOsDuration
{
    private const UNIT_SECONDS = ['w' => 604800, 'd' => 86400, 'h' => 3600, 'm' => 60, 's' => 1];

    public static function toSeconds(?string $text): int
    {
        $text = trim((string) $text);

        if ($text === '') {
            return 0;
        }

        if (preg_match('/^(?:(\d+)d\s*)?(\d+):(\d{2}):(\d{2})$/', $text, $clock) === 1) {
            return ((int) $clock[1]) * 86400 + ((int) $clock[2]) * 3600 + ((int) $clock[3]) * 60 + (int) $clock[4];
        }

        return self::fromUnits($text);
    }

    private static function fromUnits(string $text): int
    {
        preg_match_all('/(\d+)([wdhms])/', $text, $parts, PREG_SET_ORDER);

        return array_sum(array_map(
            static fn (array $part): int => ((int) $part[1]) * self::UNIT_SECONDS[$part[2]],
            $parts,
        ));
    }
}
