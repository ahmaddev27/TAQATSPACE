<?php

declare(strict_types=1);

namespace App\Support\Network;

/**
 * AR%: the days a subscriber attended divided by the workspace's operating days
 * that fall inside the subscriber's own subscription period. The period is the
 * union of the ranges of the member's subscriptions, so a day counts when any
 * one range covers it, and only attended days inside it count, which keeps the
 * result at or below 100. This is the single place AR% is computed; the scores
 * read the stored value.
 */
final class AttendanceRate
{
    /**
     * @param  list<string>  $operatingDates  local Y-m-d dates of the month on which the workspace had any attendance
     * @param  list<string>  $attendedDates  local Y-m-d dates of the month on which the subscriber attended
     * @param  list<array{0: string, 1: string|null}>  $periods  [start, end] of each subscription, end null while open-ended
     * @return float|null null when no operating day falls inside the period: there is nothing to measure against
     */
    public static function percent(array $operatingDates, array $attendedDates, array $periods): ?float
    {
        $inPeriod = static fn (string $date): bool => self::covered($periods, $date);
        $possible = count(array_filter($operatingDates, $inPeriod));

        if ($possible === 0) {
            return null;
        }

        $attended = count(array_filter(array_unique($attendedDates), $inPeriod));

        return round(min(100, $attended / $possible * 100), 2);
    }

    /** @param list<array{0: string, 1: string|null}> $periods */
    private static function covered(array $periods, string $date): bool
    {
        foreach ($periods as [$start, $end]) {
            if ($start <= $date && ($end === null || $end >= $date)) {
                return true;
            }
        }

        return false;
    }
}
