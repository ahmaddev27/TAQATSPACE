<?php

declare(strict_types=1);

namespace App\Services\Network;

use App\Support\Network\LocalDays;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeZone;

/**
 * Pure attendance maths: turns the presence intervals of ONE subscriber (all
 * devices, all routers) into one AttendanceDay per local day touched.
 *
 * Intervals are first united, so two devices online at once count once. Each
 * united interval is cut at local midnights (zone of the workspace) and at
 * every offset change inside the day, and the slots are read from the LOCAL
 * WALL CLOCK: slot n is the n-th half hour of the clock face, 0 = 00:00-00:30
 * and 47 = 23:30-24:00, so the bitmap always has 48 slots whatever the day length.
 *
 * - Short day (23 h, clocks jump forward): the skipped wall half-hours never
 *   exist, so their slots stay 0 and at most 46 slots can be set.
 * - Long day (25 h, clocks fall back): the repeated hour is folded onto the
 *   same two slots, so time spent in both passes counts once in the bitmap.
 *
 * Only the bitmap modes (Hour Bucket, Half-Hour) are affected by the folding;
 * Active Actual and Day Span use real elapsed time and stay exact.
 */
class AttendanceCalculator
{
    private const SLOT_SECONDS = 1800;

    private const DAY_SECONDS = 86400;

    public function __construct(private readonly LocalDays $days = new LocalDays) {}

    /**
     * @param  list<array{0: CarbonInterface, 1: CarbonInterface}>  $intervals  [start, end] pairs
     * @return array<string, AttendanceDay> by local date, ascending
     */
    public function calculate(array $intervals, string $timezone): array
    {
        $zone = $this->days->zone($timezone);
        $perDay = [];

        foreach ($this->union($intervals) as [$from, $to]) {
            foreach ($this->cutAtMidnights($from, $to, $zone) as [$date, $start, $end]) {
                $perDay[$date] = $this->absorb($perDay[$date] ?? null, $date, $start, $end, $zone);
            }
        }

        ksort($perDay);

        $result = [];
        foreach ($perDay as $date => $day) {
            $result[$date] = $this->toDay($date, $day);
        }

        return $result;
    }

    /**
     * @param  list<array{0: CarbonInterface, 1: CarbonInterface}>  $intervals
     * @return list<array{0: int, 1: int}>
     */
    private function union(array $intervals): array
    {
        $spans = array_map(static fn (array $pair): array => [$pair[0]->getTimestamp(), max($pair[0]->getTimestamp(), $pair[1]->getTimestamp())], $intervals);
        usort($spans, static fn (array $a, array $b): int => $a <=> $b);

        $united = [];
        foreach ($spans as [$from, $to]) {
            $last = array_key_last($united);

            if ($last !== null && $from <= $united[$last][1]) {
                $united[$last][1] = max($united[$last][1], $to);

                continue;
            }

            $united[] = [$from, $to];
        }

        return $united;
    }

    /** @return list<array{0: string, 1: int, 2: int}> [local date, start, end] with no piece crossing a local midnight */
    private function cutAtMidnights(int $from, int $to, DateTimeZone $zone): array
    {
        $pieces = [];
        $date = $this->days->dateOf($from, $zone);

        while (true) {
            $dayEnd = $this->days->startOf($this->days->next($date), $zone);
            $pieces[] = [$date, $from, min($to, $dayEnd)];

            if ($to <= $dayEnd) {
                return $pieces;
            }

            $from = $dayEnd;
            $date = $this->days->next($date);
        }
    }

    /**
     * @param  array{bitmap: int, seconds: int, first: int, last: int}|null  $day
     * @return array{bitmap: int, seconds: int, first: int, last: int}
     */
    private function absorb(?array $day, string $date, int $start, int $end, DateTimeZone $zone): array
    {
        $day ??= ['bitmap' => 0, 'seconds' => 0, 'first' => $start, 'last' => $end];

        return [
            'bitmap' => $day['bitmap'] | $this->slotsOf($date, $start, $end, $zone),
            'seconds' => $day['seconds'] + ($end - $start),
            'first' => min($day['first'], $start),
            'last' => max($day['last'], $end),
        ];
    }

    /** Bitmap of the wall-clock half hours a piece of one local day touches. */
    private function slotsOf(string $date, int $start, int $end, DateTimeZone $zone): int
    {
        $base = $this->days->wallEpoch($date);
        $bitmap = 0;

        foreach ($this->constantOffsetSegments($start, $end, $zone) as [$from, $to, $offset]) {
            $wallFrom = max(0, $from + $offset - $base);
            $wallTo = min(self::DAY_SECONDS, $to + $offset - $base);
            $bitmap |= $this->maskOf($wallFrom, $wallTo);
        }

        return $bitmap;
    }

    private function maskOf(int $wallFrom, int $wallTo): int
    {
        $first = intdiv($wallFrom, self::SLOT_SECONDS);
        // An end exactly on a slot boundary does not touch the next slot; an instant (no length) touches its own.
        $last = $wallTo > $wallFrom ? intdiv($wallTo - 1, self::SLOT_SECONDS) : $first;
        $last = min(47, max($first, $last));

        return ((1 << ($last - $first + 1)) - 1) << $first;
    }

    /** @return list<array{0: int, 1: int, 2: int}> [from, to, utc offset in seconds] */
    private function constantOffsetSegments(int $start, int $end, DateTimeZone $zone): array
    {
        $transitions = $zone->getTransitions($start, $end);
        $segments = [];

        foreach ($transitions as $index => $transition) {
            $from = $index === 0 ? $start : $transition['ts'];
            $to = $transitions[$index + 1]['ts'] ?? $end;
            $segments[] = [$from, min($to, $end), $transition['offset']];
        }

        return $segments;
    }

    /** @param array{bitmap: int, seconds: int, first: int, last: int} $day */
    private function toDay(string $date, array $day): AttendanceDay
    {
        $bitmap = $day['bitmap'];

        return new AttendanceDay(
            date: $date,
            slotsBitmap: $bitmap,
            activeActualMinutes: intdiv($day['seconds'] + 30, 60),
            daySpanMinutes: intdiv($day['last'] - $day['first'] + 30, 60),
            hourBucketHours: $this->hoursWithActivity($bitmap),
            halfHourSlots: $this->popcount($bitmap),
            firstSeenAt: CarbonImmutable::createFromTimestampUTC($day['first']),
            lastSeenAt: CarbonImmutable::createFromTimestampUTC($day['last']),
        );
    }

    private function hoursWithActivity(int $bitmap): int
    {
        $hours = 0;

        for ($hour = 0; $hour < 24; $hour++) {
            $hours += (($bitmap >> ($hour * 2)) & 0b11) !== 0 ? 1 : 0;
        }

        return $hours;
    }

    private function popcount(int $bitmap): int
    {
        $count = 0;

        for ($slot = 0; $slot < 48; $slot++) {
            $count += ($bitmap >> $slot) & 1;
        }

        return $count;
    }
}
