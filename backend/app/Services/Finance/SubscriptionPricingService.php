<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Subscription;
use Carbon\CarbonInterface;

/**
 * The price a subscription is billed per period: its negotiated price, else
 * its plan's price (promo included), else the legacy `monthly_price` that
 * every subscription made before plans existed still carries.
 */
class SubscriptionPricingService
{
    public function __construct(private readonly PlanService $plans) {}

    public function effectivePrice(Subscription $subscription, ?CarbonInterface $on = null): string
    {
        if ($subscription->custom_price !== null) {
            return $this->money($subscription->custom_price);
        }

        $plan = $subscription->plan;

        if ($plan !== null) {
            return $this->money($this->plans->effectivePrice($plan, $on ?? now()));
        }

        return $this->money($subscription->monthly_price);
    }

    private function money(string|float|int|null $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
