<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Network\GapDetector;
use Illuminate\Console\Command;

/**
 * A silent router sends nothing, so no sync would ever open its gap; this
 * sweep does, for routers of workspaces with the network module only.
 */
class DetectRouterGaps extends Command
{
    protected $signature = 'routers:detect-gaps';

    protected $description = 'Open a gap for every router that stopped syncing.';

    public function handle(GapDetector $detector): int
    {
        $this->info("Opened {$detector->detectSilent()} gap(s).");

        return self::SUCCESS;
    }
}
