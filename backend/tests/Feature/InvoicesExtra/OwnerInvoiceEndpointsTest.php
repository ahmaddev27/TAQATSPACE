<?php

declare(strict_types=1);

namespace Tests\Feature\InvoicesExtra;

use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\InvoicePaidNotification;
use App\Notifications\InvoiceReceiptRejectedNotification;
use App\Notifications\InvoiceReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OwnerInvoiceEndpointsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Workspace, 2: Subscription, 3: User}
     */
    private function ownerWorkspaceSubscription(): array
    {
        $owner = User::factory()->owner()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->freelancer()->create();
        $subscription = Subscription::factory()->create([
            'workspace_id' => $workspace->id,
            'member_id' => $member->id,
            'status' => SubscriptionStatus::Active,
            'monthly_price' => 40,
        ]);

        return [$owner, $workspace, $subscription, $member];
    }

    public function test_pay_endpoint_marks_invoice_paid_for_owning_workspace(): void
    {
        Notification::fake();
        [$owner, , $subscription, $member] = $this->ownerWorkspaceSubscription();
        $invoice = Invoice::factory()->create([
            'subscription_id' => $subscription->id,
            'amount' => '40.00',
            'status' => InvoiceStatus::Pending,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->putJson("/api/workspace/invoices/{$invoice->id}/pay");

        $response->assertOk()
            ->assertJsonPath('data.status', InvoiceStatus::Paid->value);
        $this->assertSame(InvoiceStatus::Paid, $invoice->refresh()->status);
        Notification::assertSentTo($member, InvoicePaidNotification::class);
    }

    public function test_pay_endpoint_404s_for_an_invoice_outside_the_owners_workspace(): void
    {
        Notification::fake();
        [$owner] = $this->ownerWorkspaceSubscription();

        $otherSub = Subscription::factory()->create(['status' => SubscriptionStatus::Active]);
        $foreign = Invoice::factory()->create([
            'subscription_id' => $otherSub->id,
            'status' => InvoiceStatus::Pending,
        ]);

        Sanctum::actingAs($owner);

        $this->putJson("/api/workspace/invoices/{$foreign->id}/pay")->assertNotFound();
        $this->assertSame(InvoiceStatus::Pending, $foreign->refresh()->status);
    }

    public function test_pay_endpoint_returns_422_when_invoice_already_paid(): void
    {
        Notification::fake();
        [$owner, , $subscription] = $this->ownerWorkspaceSubscription();
        $invoice = Invoice::factory()->paid()->create([
            'subscription_id' => $subscription->id,
        ]);

        Sanctum::actingAs($owner);

        $this->putJson("/api/workspace/invoices/{$invoice->id}/pay")->assertStatus(422);
    }

    public function test_approve_receipt_endpoint_confirms_paid(): void
    {
        Notification::fake();
        [$owner, , $subscription, $member] = $this->ownerWorkspaceSubscription();
        $invoice = Invoice::factory()->create([
            'subscription_id' => $subscription->id,
            'amount' => '40.00',
            'status' => InvoiceStatus::UnderReview,
        ]);

        Sanctum::actingAs($owner);

        $this->putJson("/api/workspace/invoices/{$invoice->id}/approve-receipt")
            ->assertOk()
            ->assertJsonPath('data.status', InvoiceStatus::Paid->value);
        Notification::assertSentTo($member, InvoicePaidNotification::class);
    }

    public function test_approve_receipt_endpoint_422_when_not_under_review(): void
    {
        Notification::fake();
        [$owner, , $subscription] = $this->ownerWorkspaceSubscription();
        $invoice = Invoice::factory()->create([
            'subscription_id' => $subscription->id,
            'status' => InvoiceStatus::Pending,
        ]);

        Sanctum::actingAs($owner);

        $this->putJson("/api/workspace/invoices/{$invoice->id}/approve-receipt")->assertStatus(422);
    }

    public function test_reject_receipt_endpoint_records_reason(): void
    {
        Notification::fake();
        [$owner, , $subscription, $member] = $this->ownerWorkspaceSubscription();
        $invoice = Invoice::factory()->create([
            'subscription_id' => $subscription->id,
            'status' => InvoiceStatus::UnderReview,
        ]);

        Sanctum::actingAs($owner);

        $this->putJson("/api/workspace/invoices/{$invoice->id}/reject-receipt", [
            'reason' => 'Receipt is not legible',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', InvoiceStatus::PaymentRejected->value);

        $this->assertSame('Receipt is not legible', $invoice->refresh()->receipt_rejected_reason);
        Notification::assertSentTo($member, InvoiceReceiptRejectedNotification::class);
    }

    public function test_reject_receipt_endpoint_validates_reason_required(): void
    {
        [$owner, , $subscription] = $this->ownerWorkspaceSubscription();
        $invoice = Invoice::factory()->create([
            'subscription_id' => $subscription->id,
            'status' => InvoiceStatus::UnderReview,
        ]);

        Sanctum::actingAs($owner);

        $this->putJson("/api/workspace/invoices/{$invoice->id}/reject-receipt", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');
    }

    public function test_remind_endpoint_sends_then_throttles_with_429(): void
    {
        Notification::fake();
        Cache::flush();
        [$owner, , $subscription, $member] = $this->ownerWorkspaceSubscription();
        $invoice = Invoice::factory()->overdue()->create([
            'subscription_id' => $subscription->id,
        ]);

        Sanctum::actingAs($owner);

        $this->postJson("/api/workspace/invoices/{$invoice->id}/remind")->assertOk();
        Notification::assertSentTo($member, InvoiceReminderNotification::class);

        // Second call within the 24h window is rate-limited.
        $this->postJson("/api/workspace/invoices/{$invoice->id}/remind")->assertStatus(429);
    }

    public function test_destroy_endpoint_deletes_owned_invoice(): void
    {
        [$owner, , $subscription] = $this->ownerWorkspaceSubscription();
        $invoice = Invoice::factory()->create([
            'subscription_id' => $subscription->id,
            'receipt_path' => null,
        ]);

        Sanctum::actingAs($owner);

        $this->deleteJson("/api/workspace/invoices/{$invoice->id}")->assertOk();
        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
    }

    public function test_destroy_endpoint_404s_for_foreign_invoice(): void
    {
        [$owner] = $this->ownerWorkspaceSubscription();
        $otherSub = Subscription::factory()->create(['status' => SubscriptionStatus::Active]);
        $foreign = Invoice::factory()->create(['subscription_id' => $otherSub->id]);

        Sanctum::actingAs($owner);

        $this->deleteJson("/api/workspace/invoices/{$foreign->id}")->assertNotFound();
        $this->assertDatabaseHas('invoices', ['id' => $foreign->id]);
    }

    public function test_owner_routes_reject_unauthenticated_requests(): void
    {
        $invoice = Invoice::factory()->create();

        $this->putJson("/api/workspace/invoices/{$invoice->id}/pay")->assertUnauthorized();
    }

    public function test_owner_routes_reject_a_freelancer_role(): void
    {
        $freelancer = User::factory()->freelancer()->create();
        $invoice = Invoice::factory()->create();

        Sanctum::actingAs($freelancer);

        $this->putJson("/api/workspace/invoices/{$invoice->id}/pay")->assertForbidden();
    }
}
