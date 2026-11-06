<?php

declare(strict_types=1);

namespace App\Support\Network;

use App\Models\RouterGap;
use Carbon\CarbonInterface;
use DateTimeZone;
use Illuminate\Support\Collection;

/**
 * Folds router gaps into the workspace-local days they touch. Gaps of several
 * routers are united first, so minutes covered twice are counted once; an open
 * gap runs until `$now`.
 */
final class GapDays
{
    private const SECONDS_PER_MINUTE = 60;

    public function __construct(private readonly LocalDays $days = new LocalDays) {}

    /**
     * @param  Collection<int, RouterGap>  $gaps
     * @param  string  $from  local date, Y-m-d
     * @param  string  $to  local date, Y-m-d, inclusive
     * @return array<string, int> local date => minutes without data; days without a gap are absent
     */
    public function minutesByDate(Collection $gaps, DateTimeZone $zone, string $from, string $to, CarbonInterface $now): array
    {
        $minutes = [];
        $spans = $this->united($gaps, $now);

        for ($date = $from; $date <= $to; $date = $this->days->next($date)) {
            $seconds = $this->overlap($spans, $this->days->startOf($date, $zone), $this->days->startOf($this->days->next($date), $zone));

            if ($seconds > 0) {
                $minutes[$date] = max(1, (int) ceil($seconds / self::SECONDS_PER_MINUTE));
            }
        }

        return $minutes;
    }

    /**
     * @param  Collection<int, RouterGap>  $gaps
     * @return list<array{0: int, 1: int}> sorted, non-overlapping timestamp spans
     */
    private function united(Collection $gaps, CarbonInterface $now): array
    {
        $spans = $gaps
            ->map(fn (RouterGap $gap): array => [$gap->started_at->getTimestamp(), ($gap->ended_at ?? $now)->getTimestamp()])
            ->sortBy(0)
            ->values();
        $united = [];

        foreach ($spans as [$start, $end]) {
            $last = count($united) - 1;

            if ($last >= 0 && $start <= $united[$last][1]) {
                $united[$last][1] = max($united[$last][1], $end);
            } else {
                $united[] = [$start, $end];
            }
        }

        return $united;
    }

    /** @param list<array{0: int, 1: int}> $spans */
    private function overlap(array $spans, int $from, int $to): int
    {
        $seconds = 0;

        foreach ($spans as [$start, $end]) {
            $seconds += max(0, min($end, $to) - max($start, $from));
        }

        return $seconds;
    }
}
