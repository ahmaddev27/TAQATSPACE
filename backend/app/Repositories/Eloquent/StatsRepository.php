<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\SubscriptionStatus;
use App\Models\AttendanceDaily;
use App\Models\HotspotSession;
use App\Models\StatsDailyWorkspace;
use App\Models\StatsMonthlySubscriber;
use App\Models\Subscription;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Reads the inputs of the statistics and rewrites them in bulk: no query inside a loop. */
class StatsRepository
{
    private const WRITE_CHUNK = 500;

    /** @return Collection<int, object> raw attendance rows of the local date range, inclusive */
    public function attendance(string $workspaceId, string $fromDate, string $toDate): Collection
    {
        return AttendanceDaily::query()
            ->withoutWorkspaceScope()
            ->where('workspace_id', $workspaceId)
            ->whereBetween('date', [$fromDate, $toDate])
            ->toBase()
            ->get(['subscriber_id', 'date', 'slots_bitmap', 'active_actual_minutes', 'day_span_minutes', 'hour_bucket_hours', 'half_hour_slots', 'first_seen_at', 'last_seen_at']);
    }

    /**
     * Sessions that touch [`$from`, `$to`), whether or not a subscriber is known: a device
     * that is online counts towards the concurrent peak even before it is matched to a member.
     *
     * @return Collection<int, HotspotSession>
     */
    public function sessions(string $workspaceId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return HotspotSession::query()
            ->withoutWorkspaceScope()
            ->where('workspace_id', $workspaceId)
            ->where('started_at', '<', $to)
            ->where(fn (Builder $query) => $query->whereNull('ended_at')->orWhere('ended_at', '>=', $from))
            ->get(['subscriber_id', 'started_at', 'ended_at', 'last_seen_at', 'bytes_in', 'bytes_out']);
    }

    /** @return Collection<int, object> member_id, start_date, end_date of subscriptions overlapping the date range */
    public function membershipSpans(string $workspaceId, string $fromDate, string $toDate): Collection
    {
        return Subscription::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::Expired->value, SubscriptionStatus::Suspended->value])
            ->whereDate('start_date', '<=', $toDate)
            ->where(fn (Builder $query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', $fromDate))
            ->toBase()
            ->get(['member_id', 'start_date', 'end_date']);
    }

    /** @return Collection<int, StatsDailyWorkspace> */
    public function dailyRange(string $workspaceId, string $fromDate, string $toDate): Collection
    {
        return StatsDailyWorkspace::query()
            ->withoutWorkspaceScope()
            ->where('workspace_id', $workspaceId)
            ->whereBetween('date', [$fromDate, $toDate])
            ->orderBy('date')
            ->get();
    }

    /** @param list<array<string, mixed>> $rows one row for every day of the range, so nothing stale survives */
    public function upsertDaily(array $rows): void
    {
        $update = ['active_members', 'dau', 'active_minutes', 'hour_bucket_hours', 'bytes_in', 'bytes_out', 'peak_concurrent', 'peak_hour', 'hourly_presence'];

        DB::transaction(function () use ($rows, $update): void {
            foreach (array_chunk($rows, self::WRITE_CHUNK) as $chunk) {
                StatsDailyWorkspace::query()->withoutWorkspaceScope()->upsert($chunk, ['workspace_id', 'date'], $update);
            }
        });
    }

    /**
     * Subscribers who no longer have attendance in a month must lose their row, so the
     * range is deleted first; both steps share one transaction so readers never see a gap.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function replaceMonthly(string $workspaceId, string $fromMonth, string $toMonth, array $rows): void
    {
        DB::transaction(function () use ($workspaceId, $fromMonth, $toMonth, $rows): void {
            StatsMonthlySubscriber::query()
                ->withoutWorkspaceScope()
                ->where('workspace_id', $workspaceId)
                ->whereBetween('month', [$fromMonth, $toMonth])
                ->delete();

            foreach (array_chunk($rows, self::WRITE_CHUNK) as $chunk) {
                StatsMonthlySubscriber::query()->withoutWorkspaceScope()->insert($chunk);
            }
        });
    }
}
