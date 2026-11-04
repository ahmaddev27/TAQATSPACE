<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Network\SessionBuilder;
use Illuminate\Console\Command;

/**
 * Timeout for routers that went silent: no sync means no ingest job, so
 * nothing else would ever close their open sessions.
 */
class CloseIdleSessions extends Command
{
    protected $signature = 'sessions:close-idle';

    protected $description = 'Close open hotspot sessions nobody has vouched for within the timeout.';

    public function handle(SessionBuilder $sessions): int
    {
        $this->info("Swept {$sessions->closeIdleEverywhere()} router(s).");

        return self::SUCCESS;
    }
}
