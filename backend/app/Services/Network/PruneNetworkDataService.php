<?php

declare(strict_types=1);

namespace App\Services\Network;

use App\Models\HotspotEvent;
use App\Models\RouterApiLog;
use App\Models\RouterSnapshotChunk;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Applies the network data retention policy (config/retention.php). Only
 * high-volume raw tables are touched, and never rows still waiting to be
 * processed. Deletes run in small batches so the sync tables are not locked
 * for long, which also makes repeated or interrupted runs safe.
 */
class PruneNetworkDataService
{
    /**
     * @return array{raw_events: int, snapshot_chunks: int, router_api_logs: int}
     */
    public function prune(bool $dryRun = false): array
    {
        return [
            'raw_events' => $this->run($this->rawEvents(), $dryRun),
            'snapshot_chunks' => $this->run($this->snapshotChunks(), $dryRun),
            'router_api_logs' => $this->run($this->apiLogs(), $dryRun),
        ];
    }

    /** @return Builder<HotspotEvent> */
    private function rawEvents(): Builder
    {
        return HotspotEvent::query()
            ->whereNotNull('processed_at')
            ->where('occurred_at', '<', $this->rawEventsCutoff());
    }

    /** @return Builder<RouterSnapshotChunk> */
    private function snapshotChunks(): Builder
    {
        return RouterSnapshotChunk::query()
            ->where('created_at', '<', now()->subDays($this->days('snapshot_chunks_days')))
            ->whereHas('snapshot', fn (Builder $snapshot) => $snapshot->whereNotNull('assembled_at'));
    }

    /** @return Builder<RouterApiLog> */
    private function apiLogs(): Builder
    {
        return RouterApiLog::query()
            ->where('created_at', '<', now()->subDays($this->days('router_api_logs_days')));
    }

    /**
     * An event is deletable once the end of its month is older than
     * (grace + retention) days, so the cutoff is the start of the month that
     * contains that threshold: every earlier month is complete.
     */
    private function rawEventsCutoff(): Carbon
    {
        $days = $this->days('month_close_grace_days') + $this->days('raw_events_days_after_close');

        return now()->subDays($days)->startOfMonth();
    }

    /** @param Builder<*> $query */
    private function run(Builder $query, bool $dryRun): int
    {
        if ($dryRun) {
            return $query->count();
        }

        $batchSize = max(1, (int) config('retention.batch_size'));
        $deleted = 0;

        do {
            $ids = (clone $query)->orderBy('id')->limit($batchSize)->pluck('id');
            $count = $ids->count();

            if ($count > 0) {
                $query->getModel()->newQuery()->whereKey($ids->all())->delete();
                $deleted += $count;
            }
        } while ($count === $batchSize);

        return $deleted;
    }

    private function days(string $key): int
    {
        return (int) config("retention.{$key}");
    }
}
