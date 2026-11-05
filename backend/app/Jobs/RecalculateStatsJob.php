<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Workspace;
use App\Services\Network\StatsRecalculationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Rebuilds attendance and statistics for a date range of one workspace. It runs
 * on its own queue so a long range never delays the router ingest.
 */
class RecalculateStatsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const QUEUE = 'heavy';

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(
        public readonly string $workspaceId,
        public readonly string $from,
        public readonly string $to,
    ) {
        $this->onQueue(self::QUEUE);
    }

    public function handle(StatsRecalculationService $recalculation): void
    {
        $workspace = Workspace::query()->find($this->workspaceId);

        if ($workspace !== null) {
            $recalculation->run($workspace, $this->from, $this->to);
        }
    }

    public function failed(Throwable $exception): void
    {
        $workspace = Workspace::query()->find($this->workspaceId);

        if ($workspace !== null) {
            app(StatsRecalculationService::class)->fail($workspace);
        }
    }
}
