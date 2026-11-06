<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\StatsMonthlySubscriber;
use App\Models\SubscriberScore;
use Generator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Reads the scoring inputs from stats_monthly_subscriber and rewrites the scores in bulk: no query per subscriber. */
class SubscriberScoreRepository
{
    /** Subscribers read and written per round trip. */
    public const CHUNK = 500;

    /**
     * Subscribers with statistics, a chunk at a time in subscriber order, so a large workspace is never held in memory whole.
     *
     * @return Generator<int, Collection<int, object>> chunks of subscriber_id, first_activity_at, last_activity_at over all months
     */
    public function lifetimeChunks(string $workspaceId): Generator
    {
        $after = null;

        do {
            $chunk = StatsMonthlySubscriber::query()
                ->withoutWorkspaceScope()
                ->where('workspace_id', $workspaceId)
                ->when($after !== null, fn ($query) => $query->where('subscriber_id', '>', $after))
                ->groupBy('subscriber_id')
                ->orderBy('subscriber_id')
                ->limit(self::CHUNK)
                ->toBase()
                ->get(['subscriber_id', DB::raw('MIN(first_activity_at) as first_activity_at'), DB::raw('MAX(last_activity_at) as last_activity_at')]);

            if ($chunk->isNotEmpty()) {
                yield $chunk;
                $after = $chunk->last()->subscriber_id;
            }
        } while ($chunk->count() === self::CHUNK);
    }

    /**
     * @param  list<string>  $subscriberIds
     * @return Collection<int, object> the subscribers' stored months from `$fromMonth` to `$toMonth`, inclusive
     */
    public function window(string $workspaceId, string $fromMonth, string $toMonth, array $subscriberIds): Collection
    {
        return StatsMonthlySubscriber::query()
            ->withoutWorkspaceScope()
            ->where('workspace_id', $workspaceId)
            ->whereIn('subscriber_id', $subscriberIds)
            ->whereBetween('month', [$fromMonth, $toMonth])
            ->toBase()
            ->get(['subscriber_id', 'attendance_days', 'attendance_rate', 'active_actual_minutes', 'hour_bucket_hours']);
    }

    /**
     * Writes the chunks of rows and drops the workspace's scores of subscribers that no longer have statistics,
     * so nobody keeps a stale segment. One transaction: readers never see a gap.
     *
     * @param  iterable<list<array<string, mixed>>>  $chunks  produced while the transaction is open
     * @return int rows written
     */
    public function replace(string $workspaceId, iterable $chunks): int
    {
        $update = ['loyalty_score', 'strength_score', 'segment', 'computed_at', 'components'];

        return DB::transaction(function () use ($workspaceId, $chunks, $update): int {
            $written = 0;

            foreach ($chunks as $rows) {
                SubscriberScore::query()->withoutWorkspaceScope()->upsert($rows, ['workspace_id', 'subscriber_id'], $update);
                $written += count($rows);
            }

            $this->deleteWithoutStatistics($workspaceId);

            return $written;
        });
    }

    private function deleteWithoutStatistics(string $workspaceId): void
    {
        SubscriberScore::query()
            ->withoutWorkspaceScope()
            ->where('workspace_id', $workspaceId)
            ->whereNotExists(fn (Builder $query) => $query->selectRaw('1')
                ->from('stats_monthly_subscriber')
                ->whereColumn('stats_monthly_subscriber.subscriber_id', 'subscriber_scores.subscriber_id')
                ->where('stats_monthly_subscriber.workspace_id', $workspaceId))
            ->delete();
    }
}
