<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SubscriptionLifecycleService
{
    private const CHUNK_SIZE = 200;

    public function __construct(private readonly MemberService $members) {}

    /**
     * Move every active subscription whose end date is before today to
     * expired and free its seat. Grace days are not applied yet (see 4.7).
     *
     * @return int number of subscriptions expired (or that would be, on a dry run)
     */
    public function expireDue(bool $dryRun = false): int
    {
        $query = Subscription::query()
            ->where('status', SubscriptionStatus::Active->value)
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<', now()->toDateString());

        if ($dryRun) {
            return $query->count();
        }

        $expired = 0;

        $query->with('workspace')->chunkById(self::CHUNK_SIZE, function ($subscriptions) use (&$expired): void {
            DB::transaction(function () use ($subscriptions): void {
                foreach ($subscriptions as $subscription) {
                    $subscription->update(['status' => SubscriptionStatus::Expired->value]);
                    $this->members->freeSeat($subscription->workspace, $subscription);
                }
            });

            foreach ($subscriptions->pluck('workspace_id')->unique() as $workspaceId) {
                Cache::forget("workspace:{$workspaceId}:dashboard_stats");
            }

            $expired += $subscriptions->count();
        });

        return $expired;
    }
}
