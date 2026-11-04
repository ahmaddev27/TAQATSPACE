<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\BillingMode;
use App\Enums\InvoiceStatus;
use App\Enums\PricingMethod;
use App\Enums\SubscriptionStatus;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Seat;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\InvoiceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

class SubscriptionFinancialTermsTest extends CatalogTestCase
{
    private function subscriptionIn(Workspace $workspace, array $attributes = []): Subscription
    {
        return Subscription::factory()->create([
            'workspace_id' => $workspace->id,
            'status' => SubscriptionStatus::Active,
            'monthly_price' => 40,
            ...$attributes,
        ]);
    }

    private function url(Subscription $subscription): string
    {
        return "/api/workspace/subscriptions/{$subscription->id}/financial";
    }

    public function test_owner_sets_plan_mode_price_and_flags(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $plan = Plan::factory()->create(['workspace_id' => $workspace->id, 'price' => 90]);
        $subscription = $this->subscriptionIn($workspace);
        Sanctum::actingAs($owner);

        $this->putJson($this->url($subscription), [
            'plan_id' => $plan->id,
            'billing_mode' => 'prepaid',
            'custom_price' => 70,
            'grace_days' => 5,
            'auto_renew' => true,
        ])->assertOk()
            ->assertJsonPath('data.billing_mode', 'prepaid')
            ->assertJsonPath('data.plan_code', $plan->code)
            ->assertJsonPath('data.grace_days', 5)
            ->assertJsonPath('data.auto_renew', true);

        $fresh = $subscription->fresh();
        $this->assertSame(BillingMode::Prepaid, $fresh->billing_mode);
        $this->assertTrue($fresh->isPrepaid());
        $this->assertEqualsWithDelta(70.0, (float) $fresh->custom_price, 0.001);
        $this->assertSame(1, Subscription::query()->prepaid()->count());
    }

    public function test_price_change_is_audited_with_before_and_after(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $subscription = $this->subscriptionIn($workspace);
        Sanctum::actingAs($owner);

        $this->putJson($this->url($subscription), ['custom_price' => 55])->assertOk();

        $log = AuditLog::query()->where('action', 'finance.subscription.price_changed')->firstOrFail();
        $this->assertSame('40.00', $log->changes['from']);
        $this->assertSame('55.00', $log->changes['to']);
    }

    public function test_changing_only_the_grace_days_does_not_log_a_price_change(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $subscription = $this->subscriptionIn($workspace);
        Sanctum::actingAs($owner);

        $this->putJson($this->url($subscription), ['grace_days' => 3])->assertOk();

        $this->assertFalse(AuditLog::query()->where('action', 'finance.subscription.price_changed')->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'finance.subscription.terms_updated')->exists());
    }

    public function test_contracted_hours_are_rejected_for_non_hourly_plans(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $monthly = Plan::factory()->create(['workspace_id' => $workspace->id]);
        $subscription = $this->subscriptionIn($workspace);
        Sanctum::actingAs($owner);

        $this->putJson($this->url($subscription), ['plan_id' => $monthly->id, 'contracted_hours' => 20])
            ->assertUnprocessable()->assertJsonValidationErrors('contracted_hours');
        $this->putJson($this->url($subscription), ['contracted_hours' => 20])
            ->assertUnprocessable()->assertJsonValidationErrors('contracted_hours');
    }

    public function test_contracted_hours_are_accepted_on_an_hourly_plan(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $hourly = Plan::factory()->metered(PricingMethod::Hourly)->create(['workspace_id' => $workspace->id]);
        $subscription = $this->subscriptionIn($workspace);
        Sanctum::actingAs($owner);

        $this->putJson($this->url($subscription), ['plan_id' => $hourly->id, 'contracted_hours' => 20])
            ->assertOk();

        $this->assertEqualsWithDelta(20.0, (float) $subscription->fresh()->contracted_hours, 0.001);
    }

    public function test_dedicated_seat_plan_needs_a_seat_on_the_subscription(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $dedicated = Plan::factory()->create(['workspace_id' => $workspace->id, 'is_dedicated_seat' => true]);
        $withoutSeat = $this->subscriptionIn($workspace);
        $withSeat = $this->subscriptionIn($workspace, [
            'seat_id' => Seat::factory()->create(['workspace_id' => $workspace->id])->id,
        ]);
        Sanctum::actingAs($owner);

        $this->putJson($this->url($withoutSeat), ['plan_id' => $dedicated->id])
            ->assertUnprocessable()->assertJsonValidationErrors('plan_id');
        $this->putJson($this->url($withSeat), ['plan_id' => $dedicated->id])->assertOk();
    }

    public function test_archived_plan_cannot_be_newly_assigned(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $archived = Plan::factory()->archived()->create(['workspace_id' => $workspace->id]);
        Sanctum::actingAs($owner);

        $this->putJson($this->url($this->subscriptionIn($workspace)), ['plan_id' => $archived->id])
            ->assertUnprocessable()->assertJsonValidationErrors('plan_id');
    }

    public function test_plan_of_another_workspace_is_rejected(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $foreign = Plan::factory()->create();
        Sanctum::actingAs($owner);

        $this->putJson($this->url($this->subscriptionIn($workspace)), ['plan_id' => $foreign->id])
            ->assertUnprocessable()->assertJsonValidationErrors('plan_id');
    }

    public function test_invalid_values_are_rejected(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        Sanctum::actingAs($owner);

        $this->putJson($this->url($this->subscriptionIn($workspace)), [
            'billing_mode' => 'weekly', 'custom_price' => -1, 'grace_days' => 999, 'auto_renew' => 'maybe',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['billing_mode', 'custom_price', 'grace_days', 'auto_renew']);
    }

    public function test_other_workspace_subscription_is_forbidden(): void
    {
        [$owner] = $this->owningWorkspace();
        [, $otherWorkspace] = $this->owningWorkspace();
        Sanctum::actingAs($owner);

        $this->putJson($this->url($this->subscriptionIn($otherWorkspace)), ['grace_days' => 1])->assertForbidden();
    }

    public function test_endpoint_is_hidden_when_finance_v2_is_off_and_denied_to_non_owners(): void
    {
        [$owner, $workspace] = $this->owningWorkspace(financeV2: false);
        $subscription = $this->subscriptionIn($workspace);
        Sanctum::actingAs($owner);
        $this->putJson($this->url($subscription), ['grace_days' => 1])->assertNotFound();

        Sanctum::actingAs(User::factory()->freelancer()->create());
        $this->putJson($this->url($subscription), ['grace_days' => 1])->assertForbidden();
    }

    public function test_monthly_invoice_uses_the_custom_price(): void
    {
        Notification::fake();
        [, $workspace] = $this->owningWorkspace();
        $subscription = $this->subscriptionIn($workspace, ['custom_price' => 65]);

        app(InvoiceService::class)->generateMonthlyInvoices(Carbon::today());

        $invoice = Invoice::query()->where('subscription_id', $subscription->id)->firstOrFail();
        $this->assertEqualsWithDelta(65.0, (float) $invoice->amount, 0.001);
    }

    public function test_legacy_subscription_is_invoiced_at_its_monthly_price_when_finance_v2_is_off(): void
    {
        Notification::fake();
        [, $workspace] = $this->owningWorkspace(financeV2: false);
        $subscription = $this->subscriptionIn($workspace);

        app(InvoiceService::class)->generateMonthlyInvoices(Carbon::today());

        $invoice = Invoice::query()->where('subscription_id', $subscription->id)->firstOrFail();
        $this->assertEqualsWithDelta(40.0, (float) $invoice->amount, 0.001);
        $this->assertSame(BillingMode::Postpaid, $subscription->fresh()->billing_mode);
    }

    public function test_subscription_grace_days_delay_the_overdue_flag(): void
    {
        Notification::fake();
        [, $workspace] = $this->owningWorkspace();
        $graced = $this->subscriptionIn($workspace, ['grace_days' => 5]);
        $plain = $this->subscriptionIn($workspace);
        $due = Carbon::today()->subDays(3)->toDateString();
        $gracedInvoice = Invoice::factory()->create([
            'subscription_id' => $graced->id, 'status' => InvoiceStatus::Pending, 'due_date' => $due,
        ]);
        $plainInvoice = Invoice::factory()->create([
            'subscription_id' => $plain->id, 'status' => InvoiceStatus::Pending, 'due_date' => $due,
        ]);

        $flagged = app(InvoiceService::class)->markOverdue();

        $this->assertSame(1, $flagged);
        $this->assertSame(InvoiceStatus::Pending, $gracedInvoice->refresh()->status);
        $this->assertSame(InvoiceStatus::Overdue, $plainInvoice->refresh()->status);
    }

    public function test_roster_exposes_the_financial_fields(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $this->subscriptionIn($workspace, ['custom_price' => 33]);
        Sanctum::actingAs($owner);

        $this->getJson('/api/workspace/subscriptions')
            ->assertOk()
            ->assertJsonPath('data.subscriptions.0.billing_mode', 'postpaid')
            ->assertJsonPath('data.subscriptions.0.effective_price', '33.00');
    }

    public function test_plan_in_use_is_archived_instead_of_deleted(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $plan = Plan::factory()->create(['workspace_id' => $workspace->id]);
        $this->subscriptionIn($workspace, ['plan_id' => $plan->id]);
        Sanctum::actingAs($owner);

        $this->assertTrue($plan->isInUse());
        $this->deleteJson("/api/workspace/finance/plans/{$plan->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', false);

        $this->assertNotNull(Plan::query()->find($plan->id)?->archived_at);
    }
}
