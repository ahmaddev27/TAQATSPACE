<?php

declare(strict_types=1);

namespace App\Services\Network;

use App\Models\Workspace;
use App\Repositories\Eloquent\StatsRepository;
use App\Repositories\Eloquent\SubscriberScoreRepository;
use App\Support\Network\LocalDays;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Generator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Loyalty (is the subscriber still coming and for how long) and strength (how
 * much they use and how firmly they are contracted), each 0 to 100, plus the
 * segment. Everything is read from stats_monthly_subscriber, so the attendance
 * rate (AR%) is the one StatsAggregator stored and is never computed a second
 * way here. Same inputs and same weights give the same scores.
 */
class ScoringService
{
    private const SECONDS_PER_DAY = 86400;

    public function __construct(
        private readonly SubscriberScoreRepository $scores,
        private readonly StatsRepository $stats,
        private readonly SegmentClassifier $classifier,
        private readonly LocalDays $days = new LocalDays,
    ) {}

    /** @return int subscribers scored */
    public function compute(Workspace $workspace, ?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now('UTC');
        $workspaceId = (string) $workspace->getKey();
        $config = ScoringConfig::forWorkspace($workspace);
        $zone = $this->days->zone($workspace->timezone);
        $today = $this->days->dateOf($now->getTimestamp(), $zone);
        $windowStart = CarbonImmutable::parse($today)->startOfMonth()->subMonths($config->windowMonths() - 1)->toDateString();

        $computedAt = $now->utc()->format('Y-m-d H:i:s');

        return $this->scores->replace($workspaceId, $this->rowChunks($workspaceId, $config, $zone, $today, $windowStart, $computedAt));
    }

    /** @return Generator<int, list<array<string, mixed>>> */
    private function rowChunks(string $workspaceId, ScoringConfig $config, DateTimeZone $zone, string $today, string $windowStart, string $computedAt): Generator
    {
        foreach ($this->scores->lifetimeChunks($workspaceId) as $lifetimes) {
            yield $this->scoreChunk($workspaceId, $lifetimes, $config, $zone, $today, $windowStart, $computedAt);
        }
    }

    /**
     * One chunk of subscribers costs three reads whatever its size.
     *
     * @param  Collection<int, object>  $lifetimes
     * @return list<array<string, mixed>>
     */
    private function scoreChunk(string $workspaceId, Collection $lifetimes, ScoringConfig $config, DateTimeZone $zone, string $today, string $windowStart, string $computedAt): array
    {
        $ids = $lifetimes->pluck('subscriber_id')->all();
        $window = $this->scores->window($workspaceId, $windowStart, substr($today, 0, 7).'-01', $ids)->groupBy('subscriber_id');
        $spans = $this->stats->membershipSpans($workspaceId, $windowStart, $today, $ids)->groupBy('member_id');

        return $lifetimes->map(function (object $lifetime) use ($workspaceId, $config, $zone, $today, $windowStart, $window, $spans, $computedAt): array {
            $activity = $this->activity($lifetime, $window->get($lifetime->subscriber_id, collect()), $spans->get($lifetime->subscriber_id, collect()), $zone, $today, $windowStart);

            return ['id' => (string) Str::uuid(), 'workspace_id' => $workspaceId, 'subscriber_id' => $lifetime->subscriber_id, 'computed_at' => $computedAt] + $this->encode($this->score($activity, $config));
        })->values()->all();
    }

    /**
     * @return array{loyalty: float, strength: float, segment: string, components: array<string, array<string, float|int>>}
     */
    public function score(SubscriberActivity $activity, ScoringConfig $config): array
    {
        $loyalty = $this->loyaltyComponents($activity, $config);
        $strength = $this->strengthComponents($activity, $config);
        $loyaltyScore = $this->weighted($loyalty, $config->weights('loyalty'));

        return [
            'loyalty' => $loyaltyScore,
            'strength' => $this->weighted($strength, $config->weights('strength')),
            'segment' => $this->classifier->classify($loyaltyScore, $activity->daysSinceLastActivity, $activity->tenureDays, $config)->value,
            'components' => [
                'loyalty' => $loyalty,
                'strength' => $strength,
                'metrics' => [
                    'days_since_last_activity' => $activity->daysSinceLastActivity,
                    'tenure_days' => $activity->tenureDays,
                    'attendance_days' => $activity->attendanceDays,
                    'active_minutes' => $activity->activeMinutes,
                    'hour_bucket_hours' => $activity->hourBucketHours,
                ],
            ],
        ];
    }

    /** @return array<string, float> */
    private function loyaltyComponents(SubscriberActivity $activity, ScoringConfig $config): array
    {
        $minutesPerDay = $activity->attendanceDays > 0 ? $activity->activeMinutes / $activity->attendanceDays : 0;

        return [
            'regularity' => $this->regularity($activity),
            'recency' => $this->capped(100 * (1 - $activity->daysSinceLastActivity / $config->target('recency_zero_days'))),
            'tenure' => $this->capped(100 * $activity->tenureDays / $config->target('tenure_full_days')),
            'engagement' => $this->capped(100 * $minutesPerDay / $config->target('engagement_minutes_per_day')),
        ];
    }

    /**
     * Average AR% over the months it could be measured. A month whose AR% is empty (no operating day
     * in the subscriber's period) says nothing about regularity, so it is left out, not counted as 0.
     */
    private function regularity(SubscriberActivity $activity): float
    {
        $measured = $activity->spanMonths - $activity->unmeasuredMonths;

        return $measured > 0 ? $this->capped($activity->attendanceRateSum / $measured) : 0.0;
    }

    /** @return array<string, float> */
    private function strengthComponents(SubscriberActivity $activity, ScoringConfig $config): array
    {
        return [
            'hours' => $this->capped(100 * $activity->hourBucketHours / $activity->spanMonths / $config->target('hours_per_month')),
            'days' => $this->capped(100 * $activity->attendanceDays / $activity->spanMonths / $config->target('days_per_month')),
            'contract' => $this->capped(100 * $activity->contractCoverage),
        ];
    }

    /**
     * @param  array<string, float>  $components
     * @param  array<string, float>  $weights
     */
    private function weighted(array $components, array $weights): float
    {
        $total = 0.0;

        foreach ($weights as $name => $weight) {
            $total += $components[$name] * $weight;
        }

        return round($total, 2);
    }

    private function capped(float $points): float
    {
        return round(max(0.0, min(100.0, $points)), 2);
    }

    /**
     * @param  Collection<int, object>  $months  the subscriber's stored months inside the window
     * @param  Collection<int, object>  $spans  the subscriber's subscription periods overlapping the window
     */
    private function activity(object $lifetime, Collection $months, Collection $spans, DateTimeZone $zone, string $today, string $windowStart): SubscriberActivity
    {
        $first = $this->localDate($lifetime->first_activity_at, $zone);
        $from = max($windowStart, $first);

        return new SubscriberActivity(
            daysSinceLastActivity: max(0, $this->daysBetween($this->localDate($lifetime->last_activity_at, $zone), $today)),
            tenureDays: max(0, $this->daysBetween($first, $today)),
            // A first activity dated after today (clock skew) must not shrink the span to zero or below.
            spanMonths: max(1, $this->monthIndex($today) - $this->monthIndex($from) + 1),
            attendanceRateSum: (float) $months->sum('attendance_rate'),
            unmeasuredMonths: $months->whereNull('attendance_rate')->count(),
            attendanceDays: (int) $months->sum('attendance_days'),
            activeMinutes: (int) $months->sum('active_actual_minutes'),
            hourBucketHours: (int) $months->sum('hour_bucket_hours'),
            contractCoverage: $this->coverage($spans, $from, $today),
        );
    }

    /** Stored datetimes are UTC; the local calendar day is what "days without activity" counts. */
    private function localDate(string $utcDateTime, DateTimeZone $zone): string
    {
        return $this->days->dateOf((int) strtotime($utcDateTime.' UTC'), $zone);
    }

    private function monthIndex(string $date): int
    {
        return (int) substr($date, 0, 4) * 12 + (int) substr($date, 5, 2);
    }

    /**
     * Share of the days from `$from` to `$to` (inclusive) that at least one period covers.
     *
     * @param  Collection<int, object>  $spans
     */
    private function coverage(Collection $spans, string $from, string $to): float
    {
        $start = $this->dayIndex($from);
        $end = $this->dayIndex($to);

        if ($end < $start) {
            return 0.0;
        }

        $covered = 0;
        $reached = $start - 1;

        foreach ($this->clipped($spans, $start, $end) as [$spanStart, $spanEnd]) {
            $covered += max(0, $spanEnd - max($spanStart - 1, $reached));
            $reached = max($reached, $spanEnd);
        }

        return $covered / ($end - $start + 1);
    }

    /**
     * @param  Collection<int, object>  $spans
     * @return list<array{0: int, 1: int}> day-index intervals inside [`$start`, `$end`], earliest first
     */
    private function clipped(Collection $spans, int $start, int $end): array
    {
        return $spans
            ->map(fn (object $span): array => [
                max($start, $this->dayIndex(substr((string) $span->start_date, 0, 10))),
                $span->end_date === null ? $end : min($end, $this->dayIndex(substr((string) $span->end_date, 0, 10))),
            ])
            ->filter(fn (array $interval): bool => $interval[0] <= $interval[1])
            ->sortBy(0)
            ->values()
            ->all();
    }

    private function dayIndex(string $date): int
    {
        return intdiv($this->days->wallEpoch($date), self::SECONDS_PER_DAY);
    }

    private function daysBetween(string $from, string $to): int
    {
        return $this->dayIndex($to) - $this->dayIndex($from);
    }

    /**
     * @param  array{loyalty: float, strength: float, segment: string, components: array<string, mixed>}  $score
     * @return array<string, mixed>
     */
    private function encode(array $score): array
    {
        return [
            'loyalty_score' => $score['loyalty'],
            'strength_score' => $score['strength'],
            'segment' => $score['segment'],
            'components' => json_encode($score['components']),
        ];
    }
}
