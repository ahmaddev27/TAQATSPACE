<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\StatsMonthlySubscriber;
use App\Models\SubscriberScore;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Reads the scoring inputs from stats_monthly_subscriber and rewrites the scores in bulk: no query per subscriber. */
class SubscriberScoreRepository
{
    private const WRITE_CHUNK = 500;

    /** @return Collection<int, object> subscriber_id, first_activity_at, last_activity_at over all months */
    public function lifetime(string $workspaceId): Collection
    {
        return StatsMonthlySubscriber::query()
            ->withoutWorkspaceScope()
            ->where('workspace_id', $workspaceId)
            ->groupBy('subscriber_id')
            ->toBase()
            ->get(['subscriber_id', DB::raw('MIN(first_activity_at) as first_activity_at'), DB::raw('MAX(last_activity_at) as last_activity_at')]);
    }

    /** @return Collection<int, object> the stored months from `$fromMonth` to `$toMonth`, inclusive */
    public function window(string $workspaceId, string $fromMonth, string $toMonth): Collection
    {
        return StatsMonthlySubscriber::query()
            ->withoutWorkspaceScope()
            ->where('workspace_id', $workspaceId)
            ->whereBetween('month', [$fromMonth, $toMonth])
            ->toBase()
            ->get(['subscriber_id', 'attendance_days', 'attendance_rate', 'active_actual_minutes', 'hour_bucket_hours']);
    }

    /**
     * Writes the rows and drops the workspace's scores that were not rewritten, so a subscriber whose
     * statistics are gone does not keep a stale segment. One transaction: readers never see a gap.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function replace(string $workspaceId, array $rows): void
    {
        $update = ['loyalty_score', 'strength_score', 'segment', 'computed_at', 'components'];

        DB::transaction(function () use ($workspaceId, $rows, $update): void {
            foreach (array_chunk($rows, self::WRITE_CHUNK) as $chunk) {
                SubscriberScore::query()->withoutWorkspaceScope()->upsert($chunk, ['workspace_id', 'subscriber_id'], $update);
            }

            $this->deleteExcept($workspaceId, array_column($rows, 'subscriber_id'));
        });
    }

    /** @param list<string> $keep */
    private function deleteExcept(string $workspaceId, array $keep): void
    {
        $stale = SubscriberScore::query()
            ->withoutWorkspaceScope()
            ->where('workspace_id', $workspaceId)
            ->pluck('subscriber_id')
            ->diff($keep);

        foreach ($stale->chunk(self::WRITE_CHUNK) as $chunk) {
            SubscriberScore::query()->withoutWorkspaceScope()->where('workspace_id', $workspaceId)->whereIn('subscriber_id', $chunk->all())->delete();
        }
    }
}
