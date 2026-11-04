<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Finance\SubscriptionPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SubscriptionPricingServiceTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionPricingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SubscriptionPricingService::class);
    }

    private function subscription(array $attributes = []): Subscription
    {
        return Subscription::factory()->create(['monthly_price' => 40, ...$attributes]);
    }

    public function test_custom_price_wins_over_plan_and_legacy_price(): void
    {
        $plan = Plan::factory()->create(['price' => 90]);
        $subscription = $this->subscription(['plan_id' => $plan->id, 'custom_price' => 75.5]);

        $this->assertEqualsWithDelta(75.5, (float) $this->service->effectivePrice($subscription), 0.001);
    }

    public function test_plan_price_is_used_without_a_custom_price(): void
    {
        $plan = Plan::factory()->create(['price' => 90]);
        $subscription = $this->subscription(['plan_id' => $plan->id]);

        $this->assertSame('90.00', $this->service->effectivePrice($subscription));
    }

    public function test_running_promo_price_applies_through_the_plan(): void
    {
        $plan = Plan::factory()->create([
            'price' => 90,
            'promo_price' => 60,
            'promo_until' => Carbon::today()->addDays(5),
        ]);
        $subscription = $this->subscription(['plan_id' => $plan->id]);

        $this->assertSame('60.00', $this->service->effectivePrice($subscription));
        $this->assertSame('90.00', $this->service->effectivePrice($subscription, Carbon::today()->addDays(30)));
    }

    public function test_legacy_subscription_without_plan_uses_monthly_price(): void
    {
        $this->assertSame('40.00', $this->service->effectivePrice($this->subscription()));
    }

    public function test_zero_custom_price_is_respected(): void
    {
        $this->assertSame('0.00', $this->service->effectivePrice($this->subscription(['custom_price' => 0])));
    }
}
