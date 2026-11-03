<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ProcessRouterBatch;
use App\Models\HotspotEvent;
use App\Models\RouterSnapshot;
use Illuminate\Console\Command;

/**
 * Fallback for ProcessRouterBatch: queues a run for every router that still
 * has unprocessed events or an unassembled complete snapshot (a dispatch that
 * was lost, or a run that failed).
 */
class DispatchPendingRouterBatches extends Command
{
    protected $signature = 'router-data:process-pending';

    protected $description = 'Queue processing for routers with unprocessed events or snapshots.';

    public function handle(): int
    {
        $routerIds = HotspotEvent::query()
            ->whereNull('processed_at')
            ->distinct()
            ->pluck('router_id')
            ->merge(
                RouterSnapshot::query()
                    ->whereNotNull('completed_at')
                    ->whereNull('assembled_at')
                    ->where('created_at', '>=', now()->subDays(PruneRouterSnapshotChunks::RETENTION_DAYS))
                    ->distinct()
                    ->pluck('router_id'),
            )
            ->unique();

        foreach ($routerIds as $routerId) {
            ProcessRouterBatch::dispatch($routerId);
        }

        $this->info("Queued processing for {$routerIds->count()} router(s).");

        return self::SUCCESS;
    }
}
