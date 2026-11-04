<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RouterSnapshotChunk;
use Illuminate\Console\Command;

class PruneRouterSnapshotChunks extends Command
{
    public const RETENTION_DAYS = 7;

    protected $signature = 'router-snapshots:prune';

    protected $description = 'Delete router snapshot chunks older than 7 days.';

    public function handle(): int
    {
        $deleted = RouterSnapshotChunk::query()
            ->where('created_at', '<', now()->subDays(self::RETENTION_DAYS))
            ->delete();

        $this->info("Pruned {$deleted} router snapshot chunk(s).");

        return self::SUCCESS;
    }
}
