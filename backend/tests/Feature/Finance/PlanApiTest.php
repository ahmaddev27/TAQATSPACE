<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\AuditLog;
use App\Models\Plan;
use Laravel\Sanctum\Sanctum;

class PlanApiTest extends CatalogTestCase
{
    private const URL = '/api/workspace/finance/plans';

    /**
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function payload(array $override = []): array
    {
        return [...[
            'code' => 'MONTHLY',
            'name_ar' => 'شهري',
            'name_en' => 'Monthly',
            'pricing_method' => 'monthly',
            'price' => 150.5,
        ], ...$override];
    }

    /**
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function withoutCode(array $override = []): array
    {
        return array_diff_key($this->payload($override), ['code' => true]);
    }

    public function test_owner_creates_a_plan_and_it_is_audited(): void
    {
        [$owner] = $this->owningWorkspace();
        Sanctum::actingAs($owner);

        $this->postJson(self::URL, $this->payload(['is_dedicated_seat' => true]))
            ->assertCreated()
            ->assertJsonPath('data.code', 'MONTHLY')
            ->assertJsonPath('data.price', '150.50')
            ->assertJsonPath('data.effective_price', '150.50')
            ->assertJsonPath('data.is_dedicated_seat', true);

        $this->assertTrue(AuditLog::query()->where('action', 'finance.plan.created')->exists());
    }

    public function test_daily_plan_cannot_be_a_dedicated_seat(): void
    {
        [$owner] = $this->owningWorkspace();
        Sanctum::actingAs($owner);

        $this->postJson(self::URL, $this->payload(['pricing_method' => 'daily', 'is_dedicated_seat' => true]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('is_dedicated_seat');
    }

    public function test_metered_plans_require_allowance_and_overage_price(): void
    {
        [$owner] = $this->owningWorkspace();
        Sanctum::actingAs($owner);

        foreach (['hourly', 'per_gb'] as $method) {
            $this->postJson(self::URL, $this->payload(['pricing_method' => $method]))
                ->assertJsonValidationErrors(['included_quantity', 'overage_price']);
        }

        $this->postJson(self::URL, $this->payload(['pricing_method' => 'hourly', 'included_quantity' => 20, 'overage_price' => 3]))
            ->assertCreated()
            ->assertJsonPath('data.included_quantity', '20.00')
            ->assertJsonPath('data.overage_price', '3.00');
    }

    public function test_bundle_requires_cap_and_validity(): void
    {
        [$owner] = $this->owningWorkspace();
        Sanctum::actingAs($owner);

        $this->postJson(self::URL, $this->payload(['pricing_method' => 'bundle']))
            ->assertJsonValidationErrors(['included_quantity', 'validity_days']);
        $this->postJson(self::URL, $this->payload(['pricing_method' => 'bundle', 'included_quantity' => 40, 'validity_days' => 30]))
            ->assertCreated()
            ->assertJsonPath('data.validity_days', 30);
    }

    public function test_money_is_limited_to_two_decimals_and_promo_must_be_cheaper_and_paired(): void
    {
        [$owner] = $this->owningWorkspace();
        Sanctum::actingAs($owner);

        $this->postJson(self::URL, $this->payload(['price' => 10.123]))->assertJsonValidationErrors('price');
        $this->postJson(self::URL, $this->payload(['promo_price' => 5]))->assertJsonValidationErrors('promo_until');
        $this->postJson(self::URL, $this->payload(['promo_price' => 500, 'promo_until' => '2030-01-01']))
            ->assertJsonValidationErrors('promo_price');
    }

    public function test_fields_unused_by_the_method_are_dropped(): void
    {
        [$owner] = $this->owningWorkspace();
        Sanctum::actingAs($owner);

        $this->postJson(self::URL, $this->payload(['included_quantity' => 5, 'overage_price' => 1, 'validity_days' => 9]))
            ->assertCreated()
            ->assertJsonPath('data.included_quantity', null)
            ->assertJsonPath('data.overage_price', null)
            ->assertJsonPath('data.validity_days', null);
    }

    public function test_duplicate_code_is_rejected_in_the_same_workspace_but_allowed_in_another(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        Plan::factory()->create(['workspace_id' => $workspace->id, 'code' => 'MONTHLY']);
        Sanctum::actingAs($owner);

        $this->postJson(self::URL, $this->payload())->assertJsonValidationErrors('code');

        [$otherOwner] = $this->owningWorkspace();
        Sanctum::actingAs($otherOwner);
        $this->postJson(self::URL, $this->payload())->assertCreated();
    }

    public function test_update_changes_fields_but_not_the_code(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $plan = Plan::factory()->create(['workspace_id' => $workspace->id, 'code' => 'KEEP']);
        Sanctum::actingAs($owner);

        $this->putJson(self::URL."/{$plan->id}", $this->payload(['code' => 'NEW']))->assertJsonValidationErrors('code');
        $this->putJson(self::URL."/{$plan->id}", $this->withoutCode(['price' => 99, 'pricing_method' => 'weekly']))
            ->assertOk()
            ->assertJsonPath('data.price', '99.00')
            ->assertJsonPath('data.pricing_method', 'weekly');

        $this->assertSame('KEEP', $plan->fresh()->code);
        $this->assertTrue(AuditLog::query()->where('action', 'finance.plan.updated')->exists());
    }

    public function test_running_promo_lowers_the_effective_price(): void
    {
        [$owner] = $this->owningWorkspace();
        Sanctum::actingAs($owner);

        $this->postJson(self::URL, $this->payload(['price' => 100, 'promo_price' => 80, 'promo_until' => now()->addDays(3)->toDateString()]))
            ->assertCreated()
            ->assertJsonPath('data.effective_price', '80.00');
    }

    public function test_archive_hides_the_plan_and_blocks_edits(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $plan = Plan::factory()->create(['workspace_id' => $workspace->id]);
        Sanctum::actingAs($owner);

        $this->postJson(self::URL."/{$plan->id}/archive")->assertOk()->assertJsonPath('data.is_archived', true);

        $this->getJson(self::URL)->assertJsonCount(0, 'data.items');
        $this->getJson(self::URL.'?archived=only')->assertJsonCount(1, 'data.items');
        $this->putJson(self::URL."/{$plan->id}", $this->withoutCode())->assertUnprocessable();
        $this->assertTrue(AuditLog::query()->where('action', 'finance.plan.archived')->exists());
    }

    public function test_deleting_an_unused_plan_removes_it(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $plan = Plan::factory()->create(['workspace_id' => $workspace->id]);
        Sanctum::actingAs($owner);

        $this->deleteJson(self::URL."/{$plan->id}")->assertOk()->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('plans', ['id' => $plan->id]);
        $this->assertTrue(AuditLog::query()->where('action', 'finance.plan.deleted')->exists());
    }

    public function test_search_and_method_filter(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        Plan::factory()->create(['workspace_id' => $workspace->id, 'code' => 'GOLD', 'name_en' => 'Gold']);
        Plan::factory()->metered()->create(['workspace_id' => $workspace->id, 'code' => 'HOURS']);
        Sanctum::actingAs($owner);

        $this->getJson(self::URL.'?search=gold')->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.code', 'GOLD');
        $this->getJson(self::URL.'?pricing_method=hourly')->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.code', 'HOURS');
    }

    public function test_other_workspaces_plans_are_invisible_and_immutable(): void
    {
        [$owner] = $this->owningWorkspace();
        $foreign = Plan::factory()->create();
        Sanctum::actingAs($owner);

        $this->getJson(self::URL)->assertJsonCount(0, 'data.items');
        $this->getJson(self::URL."/{$foreign->id}")->assertNotFound();
        $this->putJson(self::URL."/{$foreign->id}", $this->withoutCode())->assertNotFound();
        $this->postJson(self::URL."/{$foreign->id}/archive")->assertNotFound();
        $this->deleteJson(self::URL."/{$foreign->id}")->assertNotFound();
    }

    public function test_cashier_can_read_but_not_change_plans(): void
    {
        [, $workspace] = $this->owningWorkspace();
        $plan = Plan::factory()->create(['workspace_id' => $workspace->id]);
        Sanctum::actingAs($this->cashierFor($workspace));

        $this->getJson(self::URL)->assertOk();
        $this->getJson(self::URL."/{$plan->id}")->assertOk();
        $this->postJson(self::URL, $this->payload())->assertForbidden();
        $this->putJson(self::URL."/{$plan->id}", $this->withoutCode())->assertForbidden();
        $this->postJson(self::URL."/{$plan->id}/archive")->assertForbidden();
        $this->deleteJson(self::URL."/{$plan->id}")->assertForbidden();
    }

    public function test_flag_off_answers_404_and_seats_still_work(): void
    {
        [$owner] = $this->owningWorkspace(financeV2: false);
        Sanctum::actingAs($owner);

        $this->getJson(self::URL)->assertNotFound();
        $this->postJson(self::URL, [])->assertNotFound();
        $this->getJson('/api/workspace/seats')->assertOk();
    }
}
