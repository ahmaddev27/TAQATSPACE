<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SubscriptionLifecycleService;
use Illuminate\Console\Command;

class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire {--dry-run : Report how many subscriptions would expire without changing anything}';

    protected $description = 'Expire active subscriptions whose end date has passed and free their seats.';

    public function handle(SubscriptionLifecycleService $lifecycle): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $count = $lifecycle->expireDue($dryRun);

        $this->info($dryRun
            ? "Dry run: {$count} subscription(s) would be expired."
            : "Expired {$count} subscription(s).");

        return self::SUCCESS;
    }
}
