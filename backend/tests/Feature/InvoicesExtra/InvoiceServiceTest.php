<?php

declare(strict_types=1);

namespace Tests\Feature\InvoicesExtra;

use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\InvoiceCreatedNotification;
use App\Notifications\InvoiceOverdueNotification;
use App\Notifications\InvoicePaidNotification;
use App\Notifications\InvoiceReceiptRejectedNotification;
use App\Notifications\InvoiceReceiptSubmittedNotification;
use App\Notifications\InvoiceReminderNotification;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class InvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): InvoiceService
    {
        return app(InvoiceService::class);
    }

    /**
     * Build a workspace with an owner and an active subscription for a member.
     *
     * @return array{0: Workspace, 1: User, 2: Subscription}
     */
    private function workspaceWithSubscription(array $subAttrs = []): array
    {
        $owner = User::factory()->owner()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->freelancer()->create();

        $subscription = Subscription::factory()->create(array_merge([
            'workspace_id' => $workspace->id,
            'member_id' => $member->id,
            'status' => SubscriptionStatus::Active,
            'monthly_price' => 40,
            'end_date' => null,
        ], $subAttrs));

        return [$workspace, $member, $subscription];
    }

    private function invoiceFor(Subscription $subscription, array $attrs = []): Invoice
    {
        return Invoice::factory()->create(array_merge([
            'subscription_id' => $subscription->id,
        ], $attrs));
    }

    // ---- createManual -----------------------------------------------------

    public function test_create_manual_raises_a_pending_invoice_and_notifies_member(): void
    {
        Notification::fake();
        [$workspace, $member] = $this->workspaceWithSubscription();

        $invoice = $this->service()->createManual($workspace, [
            'member_id' => $member->id,
            'amount' => '75.50',
            'due_date' => '2026-07-15',
            'notes' => 'Extra room booking',
        ]);

        $this->assertSame(InvoiceStatus::Pending, $invoice->status);
        $this->assertSame('75.50', (string) $invoice->amount);
        $this->assertSame('Extra room booking', $invoice->notes);
        $this->assertNotEmpty($invoice->invoice_number);

        Notification::assertSentTo($member, InvoiceCreatedNotification::class);
    }

    public function test_create_manual_throws_when_member_has_no_active_subscription(): void
    {
        Notification::fake();
        $owner = User::factory()->owner()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $stranger = User::factory()->freelancer()->create();

        $this->expectException(RuntimeException::class);

        $this->service()->createManual($workspace, [
            'member_id' => $stranger->id,
            'amount' => '10.00',
            'due_date' => '2026-07-15',
        ]);
    }

    // ---- markPaid ---------------------------------------------------------

    public function test_mark_paid_sets_status_amount_and_notifies(): void
    {
        Notification::fake();
        [, $member, $subscription] = $this->workspaceWithSubscription();
        $invoice = $this->invoiceFor($subscription, [
            'amount' => '40.00',
            'amount_paid' => 0,
            'status' => InvoiceStatus::Pending,
        ]);

        $paidAt = Carbon::parse('2026-06-20 10:00:00');
        $updated = $this->service()->markPaid($invoice, $paidAt);

        $this->assertSame(InvoiceStatus::Paid, $updated->status);
        $this->assertSame('40.00', (string) $updated->amount_paid);
        $this->assertTrue($paidAt->equalTo($updated->paid_at));

        Notification::assertSentTo($member, InvoicePaidNotification::class);
    }

    public function test_mark_paid_rejects_an_already_paid_invoice(): void
    {
        Notification::fake();
        [, , $subscription] = $this->workspaceWithSubscription();
        $invoice = $this->invoiceFor($subscription, ['status' => InvoiceStatus::Paid]);

        $this->expectException(RuntimeException::class);
        $this->service()->markPaid($invoice);
    }

    // ---- markOverdue ------------------------------------------------------

    public function test_mark_overdue_flags_past_due_pending_invoices_and_notifies_both_parties(): void
    {
        Notification::fake();
        [, $member, $subscription] = $this->workspaceWithSubscription();
        $owner = $subscription->workspace->owner;

        $overdue = $this->invoiceFor($subscription, [
            'status' => InvoiceStatus::Pending,
            'due_date' => Carbon::today()->subDays(5)->toDateString(),
        ]);
        // Not yet due — must be left alone.
        $future = $this->invoiceFor($subscription, [
            'status' => InvoiceStatus::Pending,
            'due_date' => Carbon::today()->addDays(5)->toDateString(),
        ]);

        $flagged = $this->service()->markOverdue();

        $this->assertSame(1, $flagged);
        $this->assertSame(InvoiceStatus::Overdue, $overdue->refresh()->status);
        $this->assertSame(InvoiceStatus::Pending, $future->refresh()->status);

        Notification::assertSentTo($member, InvoiceOverdueNotification::class);
        Notification::assertSentTo($owner, InvoiceOverdueNotification::class);
    }

    public function test_mark_overdue_respects_grace_days(): void
    {
        Notification::fake();
        [, , $subscription] = $this->workspaceWithSubscription();

        $invoice = $this->invoiceFor($subscription, [
            'status' => InvoiceStatus::Pending,
            'due_date' => Carbon::today()->subDays(2)->toDateString(),
        ]);

        // With 3 grace days, a 2-day-past invoice is still inside the window.
        $this->assertSame(0, $this->service()->markOverdue(3));
        $this->assertSame(InvoiceStatus::Pending, $invoice->refresh()->status);
    }

    // ---- remindOverdue (cooldown) ----------------------------------------

    public function test_remind_overdue_notifies_member_once_then_respects_cooldown(): void
    {
        Notification::fake();
        Cache::flush();
        [, $member, $subscription] = $this->workspaceWithSubscription();

        $this->invoiceFor($subscription, [
            'status' => InvoiceStatus::Overdue,
            'due_date' => Carbon::today()->subDays(10)->toDateString(),
        ]);

        $first = $this->service()->remindOverdue();
        $this->assertSame(1, $first);

        // Within cooldown window — no further reminder is sent.
        $second = $this->service()->remindOverdue();
        $this->assertSame(0, $second);

        Notification::assertSentToTimes($member, InvoiceOverdueNotification::class, 1);
    }

    public function test_remind_overdue_also_chases_partially_paid_past_due_invoices(): void
    {
        Notification::fake();
        Cache::flush();
        [, $member, $subscription] = $this->workspaceWithSubscription();

        $this->invoiceFor($subscription, [
            'status' => InvoiceStatus::PartiallyPaid,
            'due_date' => Carbon::today()->subDays(3)->toDateString(),
        ]);

        $this->assertSame(1, $this->service()->remindOverdue());
        Notification::assertSentTo($member, InvoiceOverdueNotification::class);
    }

    // ---- sendReminder (24h cooldown) -------------------------------------

    public function test_send_reminder_dispatches_once_and_blocks_within_24h(): void
    {
        Notification::fake();
        Cache::flush();
        [, $member, $subscription] = $this->workspaceWithSubscription();
        $invoice = $this->invoiceFor($subscription, ['status' => InvoiceStatus::Overdue]);

        $this->service()->sendReminder($invoice);
        Notification::assertSentTo($member, InvoiceReminderNotification::class);

        $this->expectException(RuntimeException::class);
        $this->service()->sendReminder($invoice);
    }

    // ---- submitReceipt / approveReceipt / rejectReceipt ------------------

    public function test_submit_receipt_stores_file_moves_to_under_review_and_notifies_owner(): void
    {
        Notification::fake();
        Storage::fake('public');
        [, , $subscription] = $this->workspaceWithSubscription();
        $owner = $subscription->workspace->owner;
        $invoice = $this->invoiceFor($subscription, [
            'status' => InvoiceStatus::Pending,
            'receipt_rejected_reason' => 'old reason',
        ]);

        $file = UploadedFile::fake()->image('receipt.jpg');
        $updated = $this->service()->submitReceipt($invoice, $file);

        $this->assertSame(InvoiceStatus::UnderReview, $updated->status);
        $this->assertNotNull($updated->receipt_path);
        $this->assertNull($updated->receipt_rejected_reason);
        Storage::disk('public')->assertExists($updated->receipt_path);

        Notification::assertSentTo($owner, InvoiceReceiptSubmittedNotification::class);
    }

    public function test_approve_receipt_marks_paid_when_under_review(): void
    {
        Notification::fake();
        Storage::fake('public');
        [, $member, $subscription] = $this->workspaceWithSubscription();
        $invoice = $this->invoiceFor($subscription, [
            'status' => InvoiceStatus::UnderReview,
            'amount' => '40.00',
        ]);

        $updated = $this->service()->approveReceipt($invoice);

        $this->assertSame(InvoiceStatus::Paid, $updated->status);
        $this->assertNotNull($updated->receipt_reviewed_at);
        Notification::assertSentTo($member, InvoicePaidNotification::class);
    }

    public function test_approve_receipt_throws_when_not_under_review(): void
    {
        Notification::fake();
        [, , $subscription] = $this->workspaceWithSubscription();
        $invoice = $this->invoiceFor($subscription, ['status' => InvoiceStatus::Pending]);

        $this->expectException(RuntimeException::class);
        $this->service()->approveReceipt($invoice);
    }

    public function test_reject_receipt_records_reason_and_notifies_member(): void
    {
        Notification::fake();
        [, $member, $subscription] = $this->workspaceWithSubscription();
        $invoice = $this->invoiceFor($subscription, ['status' => InvoiceStatus::UnderReview]);

        $updated = $this->service()->rejectReceipt($invoice, 'Blurry image');

        $this->assertSame(InvoiceStatus::PaymentRejected, $updated->status);
        $this->assertSame('Blurry image', $updated->receipt_rejected_reason);
        $this->assertNotNull($updated->receipt_reviewed_at);
        Notification::assertSentTo($member, InvoiceReceiptRejectedNotification::class);
    }

    public function test_reject_receipt_throws_when_not_under_review(): void
    {
        [, , $subscription] = $this->workspaceWithSubscription();
        $invoice = $this->invoiceFor($subscription, ['status' => InvoiceStatus::Overdue]);

        $this->expectException(RuntimeException::class);
        $this->service()->rejectReceipt($invoice, 'reason');
    }

    // ---- generateMonthlyInvoices (idempotent) ----------------------------

    public function test_generate_monthly_invoices_creates_one_per_active_subscription(): void
    {
        Notification::fake();
        [, $member, $subscription] = $this->workspaceWithSubscription();

        $month = Carbon::parse('2026-07-10');
        $created = $this->service()->generateMonthlyInvoices($month);

        $this->assertSame(1, $created);
        $this->assertDatabaseHas('invoices', [
            'subscription_id' => $subscription->id,
            'status' => InvoiceStatus::Pending->value,
        ]);
        Notification::assertSentTo($member, InvoiceCreatedNotification::class);
    }

    public function test_generate_monthly_invoices_is_idempotent_for_the_same_month(): void
    {
        Notification::fake();
        [, , $subscription] = $this->workspaceWithSubscription();
        $month = Carbon::parse('2026-07-10');

        $this->assertSame(1, $this->service()->generateMonthlyInvoices($month));
        // Second run for the same month must not create a duplicate.
        $this->assertSame(0, $this->service()->generateMonthlyInvoices($month));

        $this->assertSame(1, Invoice::where('subscription_id', $subscription->id)->count());
    }

    public function test_generate_monthly_invoices_skips_inactive_subscriptions(): void
    {
        Notification::fake();
        $owner = User::factory()->owner()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        Subscription::factory()->create([
            'workspace_id' => $workspace->id,
            'status' => SubscriptionStatus::Cancelled,
        ]);

        $this->assertSame(0, $this->service()->generateMonthlyInvoices(Carbon::parse('2026-07-10')));
    }

    // ---- createForRenewal -------------------------------------------------

    public function test_create_for_renewal_raises_a_pending_invoice_and_notifies(): void
    {
        Notification::fake();
        [, $member, $subscription] = $this->workspaceWithSubscription();
        $periodEnd = Carbon::parse('2026-08-31');

        $invoice = $this->service()->createForRenewal($subscription, $periodEnd);

        $this->assertSame(InvoiceStatus::Pending, $invoice->status);
        $this->assertSame('2026-08-31', $invoice->due_date->toDateString());
        Notification::assertSentTo($member, InvoiceCreatedNotification::class);
    }

    public function test_create_for_renewal_is_idempotent_for_the_same_due_date(): void
    {
        Notification::fake();
        [, , $subscription] = $this->workspaceWithSubscription();
        $periodEnd = Carbon::parse('2026-08-31');

        $first = $this->service()->createForRenewal($subscription, $periodEnd);
        $second = $this->service()->createForRenewal($subscription, $periodEnd);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, Invoice::where('subscription_id', $subscription->id)->count());
        // Notification only fires for the freshly created invoice.
        Notification::assertSentToTimes($member = $subscription->member, InvoiceCreatedNotification::class, 1);
    }
}
