<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\PricingMethod;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Finance\PlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PlanServiceTest extends TestCase
{
    use RefreshDatabase;

    private PlanService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PlanService::class);
    }

    /**
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function data(PricingMethod $method, array $override = []): array
    {
        return [...[
            'code' => 'P-'.$method->value,
            'name_ar' => 'خطة',
            'name_en' => 'Plan',
            'pricing_method' => $method->value,
            'price' => 100,
            'included_quantity' => 10,
            'overage_price' => 2,
            'validity_days' => 30,
        ], ...$override];
    }

    public function test_every_pricing_method_can_be_created(): void
    {
        $workspace = Workspace::factory()->create();
        $actor = User::factory()->owner()->create();

        foreach (PricingMethod::cases() as $method) {
            $plan = $this->service->create($workspace, $this->data($method), $actor);
            $this->assertSame($method, $plan->fresh()->pricing_method);
        }

        $this->assertSame(6, Plan::withoutGlobalScopes()->where('workspace_id', $workspace->id)->count());
    }

    public function test_fixed_period_plans_keep_no_allowance_fields(): void
    {
        $workspace = Workspace::factory()->create();
        $actor = User::factory()->owner()->create();

        $plan = $this->service->create($workspace, $this->data(PricingMethod::Daily), $actor);

        $this->assertNull($plan->included_quantity);
        $this->assertNull($plan->overage_price);
        $this->assertNull($plan->validity_days);
    }

    public function test_dedicated_seat_is_only_allowed_for_monthly_weekly_and_bundle(): void
    {
        $workspace = Workspace::factory()->create();
        $actor = User::factory()->owner()->create();

        foreach ([PricingMethod::Monthly, PricingMethod::Weekly, PricingMethod::Bundle] as $method) {
            $plan = $this->service->create($workspace, $this->data($method, ['is_dedicated_seat' => true]), $actor);
            $this->assertTrue($plan->is_dedicated_seat);
        }

        foreach ([PricingMethod::Daily, PricingMethod::Hourly, PricingMethod::PerGb] as $method) {
            try {
                $this->service->create($workspace, $this->data($method, ['is_dedicated_seat' => true]), $actor);
                $this->fail("{$method->value} accepted a dedicated seat");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('is_dedicated_seat', $e->errors());
            }
        }
    }

    public function test_effective_price_follows_the_promotion_window(): void
    {
        $plan = Plan::factory()->make([
            'price' => 100,
            'promo_price' => 70,
            'promo_until' => Carbon::parse('2026-12-31 23:59:59'),
        ]);

        $this->assertSame('70.00', $this->service->effectivePrice($plan, Carbon::parse('2026-12-31 10:00')));
        $this->assertSame('100.00', $this->service->effectivePrice($plan, Carbon::parse('2027-01-01 00:00')));
        $this->assertSame('100.00', $this->service->effectivePrice(Plan::factory()->make(['price' => 100]), now()));
    }

    public function test_a_date_only_promo_end_runs_through_that_day(): void
    {
        $workspace = Workspace::factory()->create();
        $actor = User::factory()->owner()->create();

        $plan = $this->service->create($workspace, $this->data(PricingMethod::Monthly, [
            'promo_price' => 60,
            'promo_until' => '2026-12-31',
        ]), $actor);

        $this->assertSame('60.00', $this->service->effectivePrice($plan, Carbon::parse('2026-12-31 18:00')));
    }

    public function test_archive_is_idempotent_and_remove_deletes_an_unused_plan(): void
    {
        $workspace = Workspace::factory()->create();
        $actor = User::factory()->owner()->create();
        $plan = $this->service->create($workspace, $this->data(PricingMethod::Monthly), $actor);

        $archivedAt = $this->service->archive($plan, $actor)->archived_at;
        $this->assertEquals($archivedAt, $this->service->archive($plan, $actor)->archived_at);

        $result = $this->service->remove($plan, $actor);
        $this->assertTrue($result['deleted']);
        $this->assertDatabaseMissing('plans', ['id' => $plan->id]);
    }
}
