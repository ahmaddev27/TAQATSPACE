<?php

declare(strict_types=1);

namespace App\Support\Network;

/** The highest number of intervals open at the same instant. */
final class ConcurrencySweep
{
    /**
     * Intervals are half-open [start, end), so one that ends exactly when
     * another starts never counts as overlapping it.
     *
     * @param  list<array{0: int, 1: int}>  $intervals  UNIX timestamps
     */
    public static function peak(array $intervals): int
    {
        $events = [];

        foreach ($intervals as [$start, $end]) {
            if ($end > $start) {
                $events[] = [$start, 1];
                $events[] = [$end, -1];
            }
        }

        // At equal time the -1 sorts first, which is what makes the interval half-open.
        usort($events, static fn (array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        $open = 0;
        $peak = 0;

        foreach ($events as [, $delta]) {
            $open += $delta;
            $peak = max($peak, $open);
        }

        return $peak;
    }
}
