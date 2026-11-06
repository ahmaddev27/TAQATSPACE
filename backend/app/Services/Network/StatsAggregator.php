<?php

declare(strict_types=1);

namespace App\Services\Network;

use App\Models\Workspace;
use App\Repositories\Eloquent\StatsRepository;
use App\Support\Network\AttendanceRate;
use App\Support\Network\ConcurrencySweep;
use App\Support\Network\LocalDays;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Turns attendance_daily and hotspot_sessions into the dashboard tables. A
 * range is recomputed from scratch and written over the old rows, so running
 * it twice on the same data gives identical numbers. Daily rows cover exactly
 * the requested days; monthly rows cover every month the range touches,
 * because a month's figures are meaningless when only part of it is summed.
 * Weekly figures are never stored: weekly() folds the daily rows at read time.
 */
class StatsAggregator
{
    private const HOURS = 24;

    public function __construct(
        private readonly StatsRepository $repository,
        private readonly LocalDays $days = new LocalDays,
    ) {}

    /**
     * @param  string  $from  local date, Y-m-d
     * @param  string  $to  local date, Y-m-d, inclusive
     * @return array{days: int, subscriber_months: int} rows written
     */
    public function aggregate(Workspace $workspace, string $from, string $to): array
    {
        $workspaceId = (string) $workspace->getKey();
        $zone = $this->days->zone($workspace->timezone);
        $firstMonth = CarbonImmutable::parse($from)->startOfMonth()->toDateString();
        $lastDay = CarbonImmutable::parse($to)->endOfMonth()->toDateString();

        $attendance = $this->repository->attendance($workspaceId, $firstMonth, $lastDay);
        $sessions = new SessionDayIndex(
            $this->repository->sessions(
                $workspaceId,
                CarbonImmutable::createFromTimestampUTC($this->days->startOf($firstMonth, $zone)),
                CarbonImmutable::createFromTimestampUTC($this->days->startOf($this->days->next($lastDay), $zone)),
            ),
            $zone,
            $firstMonth,
            $lastDay,
            $this->days,
        );

        $daily = $this->dailyRows($workspaceId, $from, $to, $attendance, $sessions);
        $monthly = $this->monthlyRows($workspaceId, $attendance, $sessions);

        $this->repository->upsertDaily($daily);
        $this->repository->replaceMonthly($workspaceId, $firstMonth, CarbonImmutable::parse($to)->startOfMonth()->toDateString(), $monthly);

        return ['days' => count($daily), 'subscriber_months' => count($monthly)];
    }

    /**
     * Daily rows folded into ISO weeks (Monday first); a week at the edge of the range holds only the days inside it.
     *
     * @return list<array<string, mixed>>
     */
    public function weekly(Workspace $workspace, string $from, string $to): array
    {
        $weeks = $this->repository
            ->dailyRange((string) $workspace->getKey(), $from, $to)
            ->groupBy(fn ($day): string => CarbonImmutable::parse($day->date)->startOfWeek(CarbonInterface::MONDAY)->toDateString());

        return $weeks->map(fn (Collection $days, string $weekStart): array => [
            'week_start' => $weekStart,
            'days' => $days->count(),
            'dau_total' => $days->sum('dau'),
            'dau_average' => round($days->avg('dau'), 2),
            'active_minutes' => $days->sum('active_minutes'),
            'hour_bucket_hours' => $days->sum('hour_bucket_hours'),
            'bytes_in' => $days->sum('bytes_in'),
            'bytes_out' => $days->sum('bytes_out'),
            'peak_concurrent' => $days->max('peak_concurrent'),
        ])->values()->all();
    }

    /**
     * @param  Collection<int, object>  $attendance
     * @return list<array<string, mixed>>
     */
    private function dailyRows(string $workspaceId, string $from, string $to, Collection $attendance, SessionDayIndex $sessions): array
    {
        $byDate = $attendance->groupBy(fn (object $row): string => $this->dateOf($row->date));
        $members = $this->repository->membershipSpans($workspaceId, $from, $to);
        $rows = [];

        for ($date = $from; $date <= $to; $date = $this->days->next($date)) {
            $rows[] = $this->dailyRow($workspaceId, $date, $byDate->get($date, collect()), $sessions, $members);
        }

        return $rows;
    }

    /**
     * @param  Collection<int, object>  $attendance  the day's attendance rows
     * @param  Collection<int, object>  $spans
     * @return array<string, mixed>
     */
    private function dailyRow(string $workspaceId, string $date, Collection $attendance, SessionDayIndex $sessions, Collection $spans): array
    {
        $presence = $this->hourlyPresence($attendance);
        [$bytesIn, $bytesOut] = $sessions->traffic($date);

        return [
            'workspace_id' => $workspaceId,
            'date' => $date,
            'active_members' => $spans->filter(fn (object $span): bool => $this->covers($span, $date))->pluck('member_id')->unique()->count(),
            'dau' => $attendance->count(),
            'active_minutes' => (int) $attendance->sum('active_actual_minutes'),
            'hour_bucket_hours' => (int) $attendance->sum('hour_bucket_hours'),
            'bytes_in' => $bytesIn,
            'bytes_out' => $bytesOut,
            'peak_concurrent' => ConcurrencySweep::peak($sessions->intervals($date)),
            'peak_hour' => $attendance->isEmpty() ? null : array_search(max($presence), $presence, true),
            'hourly_presence' => json_encode($presence),
        ];
    }

    /**
     * Subscribers present in each local hour, read from the half-hour bitmaps.
     *
     * @param  Collection<int, object>  $attendance
     * @return list<int>
     */
    private function hourlyPresence(Collection $attendance): array
    {
        $hours = array_fill(0, self::HOURS, 0);

        foreach ($attendance as $row) {
            for ($hour = 0; $hour < self::HOURS; $hour++) {
                // Slots 2h and 2h+1 are the two halves of hour h.
                $hours[$hour] += (((int) $row->slots_bitmap >> ($hour * 2)) & 0b11) !== 0 ? 1 : 0;
            }
        }

        return $hours;
    }

    private function covers(object $span, string $date): bool
    {
        return $this->dateOf($span->start_date) <= $date
            && ($span->end_date === null || $this->dateOf($span->end_date) >= $date);
    }

    /**
     * @param  Collection<int, object>  $attendance
     * @return list<array<string, mixed>>
     */
    private function monthlyRows(string $workspaceId, Collection $attendance, SessionDayIndex $sessions): array
    {
        if ($attendance->isEmpty()) {
            return [];
        }

        // AR% compares a subscriber with the days the workspace actually had attendance, so a quiet or
        // unfinished month is not punished for days nobody could have attended.
        $operatingDates = $attendance->groupBy(fn (object $row): string => $this->monthOf($row->date))
            ->map(fn (Collection $rows): array => $rows->pluck('date')->map(fn ($date): string => $this->dateOf($date))->unique()->values()->all());
        $periods = $this->subscriptionPeriods($workspaceId, $this->dateOf($attendance->min('date')), $this->dateOf($attendance->max('date')));

        return $attendance
            ->groupBy(fn (object $row): string => $row->subscriber_id.'|'.$this->monthOf($row->date))
            ->map(function (Collection $rows, string $key) use ($workspaceId, $operatingDates, $periods, $sessions): array {
                [$subscriberId, $month] = explode('|', $key);
                $rate = AttendanceRate::percent($operatingDates->get($month, []), $rows->map(fn (object $row): string => $this->dateOf($row->date))->all(), $periods->get($subscriberId, []));

                return $this->monthlyRow($workspaceId, $subscriberId, $month, $rows, $rate, $sessions);
            })
            ->values()
            ->all();
    }

    /**
     * The date ranges of each member's subscriptions, picked the way `covers()` picks the active members of a day.
     *
     * @return Collection<string, list<array{0: string, 1: string|null}>> member id => [start, end] ranges
     */
    private function subscriptionPeriods(string $workspaceId, string $firstDate, string $lastDate): Collection
    {
        return $this->repository->membershipSpans($workspaceId, $firstDate, $lastDate)
            ->groupBy('member_id')
            ->map(fn (Collection $spans): array => $spans->map(fn (object $span): array => [$this->dateOf($span->start_date), $span->end_date === null ? null : $this->dateOf($span->end_date)])->values()->all());
    }

    /**
     * @param  Collection<int, object>  $rows  the subscriber's days of the month
     * @param  float|null  $attendanceRate  AR%, null when the subscriber's period holds no operating day
     * @return array<string, mixed>
     */
    private function monthlyRow(string $workspaceId, string $subscriberId, string $month, Collection $rows, ?float $attendanceRate, SessionDayIndex $sessions): array
    {
        [$bytesIn, $bytesOut] = $sessions->subscriberTraffic($subscriberId, $month);

        return [
            'workspace_id' => $workspaceId,
            'subscriber_id' => $subscriberId,
            'month' => $month,
            'attendance_days' => $rows->count(),
            'attendance_rate' => $attendanceRate,
            'active_actual_minutes' => (int) $rows->sum('active_actual_minutes'),
            'day_span_minutes' => (int) $rows->sum('day_span_minutes'),
            'hour_bucket_hours' => (int) $rows->sum('hour_bucket_hours'),
            'half_hour_slots' => (int) $rows->sum('half_hour_slots'),
            'bytes_in' => $bytesIn,
            'bytes_out' => $bytesOut,
            'first_activity_at' => (string) $rows->min('first_seen_at'),
            'last_activity_at' => (string) $rows->max('last_seen_at'),
        ];
    }

    private function monthOf(mixed $date): string
    {
        return substr($this->dateOf($date), 0, 7).'-01';
    }

    /** Date columns arrive as 'Y-m-d' or 'Y-m-d H:i:s' depending on the engine. */
    private function dateOf(mixed $date): string
    {
        return substr((string) $date, 0, 10);
    }
}
