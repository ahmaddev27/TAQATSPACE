<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RouterApiLog;
use Illuminate\Console\Command;

class PruneRouterApiLogs extends Command
{
    public const RETENTION_DAYS = 14;

    protected $signature = 'router-logs:prune';

    protected $description = 'Delete router API request logs older than 14 days.';

    public function handle(): int
    {
        $deleted = RouterApiLog::query()
            ->where('created_at', '<', now()->subDays(self::RETENTION_DAYS))
            ->delete();

        $this->info("Pruned {$deleted} router API log(s).");

        return self::SUCCESS;
    }
}
