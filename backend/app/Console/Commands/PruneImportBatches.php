<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Finance\Import\ImportService;
use Illuminate\Console\Command;

class PruneImportBatches extends Command
{
    public const RETENTION_DAYS = 7;

    protected $signature = 'imports:prune';

    protected $description = 'Delete previewed XLSX imports (and their parked rows) that nobody committed within 7 days.';

    public function handle(ImportService $imports): int
    {
        $pruned = $imports->pruneAbandonedPreviews(self::RETENTION_DAYS);

        $this->info("Pruned {$pruned} abandoned import preview(s).");

        return self::SUCCESS;
    }
}
