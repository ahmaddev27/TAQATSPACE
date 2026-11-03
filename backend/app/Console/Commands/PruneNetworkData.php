<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Network\PruneNetworkDataService;
use Illuminate\Console\Command;

class PruneNetworkData extends Command
{
    protected $signature = 'network:prune {--dry-run : Print the counts without deleting anything}';

    protected $description = 'Apply the network data retention policy: processed raw events, snapshot chunks and router API logs.';

    public function handle(PruneNetworkDataService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $verb = $dryRun ? 'Would prune' : 'Pruned';
        foreach ($service->prune($dryRun) as $table => $count) {
            $this->info("{$verb} {$count} row(s) from {$table}.");
        }

        return self::SUCCESS;
    }
}
