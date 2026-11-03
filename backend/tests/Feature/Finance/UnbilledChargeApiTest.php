<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\UnbilledChargeStatus;
use App\Models\AuditLog;
use App\Models\Subscription;
use App\Models\UnbilledCharge;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

class UnbilledChargeApiTest extends CatalogTestCase
{
    private const URL = '/api/workspace/finance/unbilled-charges';

    private function subscriber(string $workspaceId): User
    {
        $member = User::factory()->create();
        Subscription::factory()->create(['member_id' => $member->id, 'workspace_id' => $workspaceId]);

        return $member;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(User $customer, array $override = []): array
    {
        return [...[
            'customer_id' => $customer->id,
            'description' => 'Overtime hour',
            'qty' => 2,
            'unit_price' => 10.5,
        ], ...$override];
    }

    public function test_owner_records_a_manual_charge_with_a_computed_amount_and_it_is_audited(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $member = $this->subscriber($workspace->id);
        Sanctum::actingAs($owner);

        $this->postJson(self::URL, $this->payload($member, ['amount' => 1, 'source' => 'pos', 'status' => 'invoiced']))
            ->assertCreated()
            ->assertJsonPath('data.amount', '21.00')
            ->assertJsonPath('data.source', 'manual')
            ->assertJsonPath('data.status', 'open');

        $this->assertTrue(AuditLog::query()->where('action', 'finance.unbilled.recorded')->exists());
    }

    public function test_posting_the_same_source_ref_twice_keeps_a_single_charge(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $member = $this->subscriber($workspace->id);
        Sanctum::actingAs($owner);

        $this->postJson(self::URL, $this->payload($member, ['source_ref' => 'X-1']))->assertCreated();
        $this->postJson(self::URL, $this->payload($member, ['source_ref' => 'X-1']))->assertOk();

        $this->assertSame(1, UnbilledCharge::query()->count());
    }

    public function test_customer_must_belong_to_the_workspace_and_money_has_two_decimals(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        Sanctum::actingAs($owner);

        $stranger = User::factory()->create();
        $this->postJson(self::URL, $this->payload($stranger))->assertUnprocessable()->assertJsonValidationErrors('customer_id');

        $member = $this->subscriber($workspace->id);
        $this->postJson(self::URL, $this->payload($member, ['unit_price' => 1.234]))
            ->assertUnprocessable()->assertJsonValidationErrors('unit_price');
        $this->postJson(self::URL, $this->payload($member, ['qty' => 0]))
            ->assertUnprocessable()->assertJsonValidationErrors('qty');
    }

    public function test_void_open_charge_succeeds_and_invoiced_charge_is_422(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        Sanctum::actingAs($owner);
        $open = UnbilledCharge::factory()->create(['workspace_id' => $workspace->id]);
        $invoiced = UnbilledCharge::factory()->status(UnbilledChargeStatus::Invoiced)->create(['workspace_id' => $workspace->id]);

        $this->deleteJson(self::URL.'/'.$open->id)->assertOk()->assertJsonPath('data.status', 'voided');
        $this->deleteJson(self::URL.'/'.$invoiced->id)->assertUnprocessable();

        $this->assertTrue(AuditLog::query()->where('action', 'finance.unbilled.voided')->exists());
    }

    public function test_listing_is_isolated_per_workspace_and_filterable(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        [, $other] = $this->owningWorkspace();
        $mine = UnbilledCharge::factory()->create(['workspace_id' => $workspace->id, 'source' => 'pos']);
        UnbilledCharge::factory()->status(UnbilledChargeStatus::Voided)->create(['workspace_id' => $workspace->id]);
        $foreign = UnbilledCharge::factory()->create(['workspace_id' => $other->id]);
        Sanctum::actingAs($owner);

        $ids = collect($this->getJson(self::URL)->assertOk()->json('data.items'))->pluck('id');
        $this->assertCount(2, $ids);
        $this->assertFalse($ids->contains($foreign->id));

        $filtered = $this->getJson(self::URL.'?source=pos&status=open&customer_id='.$mine->customer_id)->json('data.items');
        $this->assertSame([$mine->id], collect($filtered)->pluck('id')->all());

        $this->deleteJson(self::URL.'/'.$foreign->id)->assertNotFound();
    }

    public function test_permissions(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $member = $this->subscriber($workspace->id);
        $charge = UnbilledCharge::factory()->create(['workspace_id' => $workspace->id]);

        $this->getJson(self::URL)->assertUnauthorized();

        Sanctum::actingAs($this->cashierFor($workspace, []));
        $this->getJson(self::URL)->assertOk();
        $this->postJson(self::URL, $this->payload($member))->assertForbidden();
        $this->deleteJson(self::URL.'/'.$charge->id)->assertForbidden();

        Sanctum::actingAs($member);
        $this->getJson(self::URL)->assertForbidden();
    }

    public function test_hidden_when_finance_v2_is_off(): void
    {
        [$owner] = $this->owningWorkspace(financeV2: false);
        Sanctum::actingAs($owner);

        $this->getJson(self::URL)->assertNotFound();
    }
}
