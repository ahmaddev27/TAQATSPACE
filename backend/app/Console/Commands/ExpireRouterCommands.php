<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Router\RouterCommandService;
use Illuminate\Console\Command;

class ExpireRouterCommands extends Command
{
    protected $signature = 'router-commands:expire';

    protected $description = 'Mark router commands past their expiry as expired.';

    public function handle(RouterCommandService $commands): int
    {
        $this->info("Expired {$commands->expireDue()} router command(s).");

        return self::SUCCESS;
    }
}
