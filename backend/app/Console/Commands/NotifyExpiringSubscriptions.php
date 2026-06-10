<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Notifications\SubscriptionExpiringNotification;
use Illuminate\Console\Command;

/**
 * Notify members whose active subscription expires exactly `--days` days from
 * today. Matching the exact day (not a range) means a member gets a single
 * reminder, so it is safe to run daily from the scheduler.
 */
class NotifyExpiringSubscriptions extends Command
{
    protected $signature = 'subscriptions:notify-expiring {--days=3}';

    protected $description = 'Notify members whose active subscription expires in the given number of days.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $targetDate = now()->startOfDay()->addDays($days)->toDateString();

        $subscriptions = Subscription::query()
            ->where('status', SubscriptionStatus::Active->value)
            ->whereDate('end_date', $targetDate)
            ->with(['member', 'workspace:id,name'])
            ->get();

        $notified = 0;

        foreach ($subscriptions as $subscription) {
            if ($subscription->member === null) {
                continue;
            }

            $subscription->member->notify(
                new SubscriptionExpiringNotification($subscription, $days),
            );
            $notified++;
        }

        $this->info("Notified {$notified} member(s) of subscriptions expiring on {$targetDate}.");

        return self::SUCCESS;
    }
}
