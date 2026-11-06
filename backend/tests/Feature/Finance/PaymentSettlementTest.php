<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\DocStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Receipt;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\InvoicePaidNotification;
use App\Services\Finance\CreditNoteService;
use App\Services\Finance\ReceiptService;
use App\Services\InvoiceService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * The "pay what is left" and "undo the payment" actions with finance v2 on:
 * one money-state step decides the status, a pending cheque is never paid
 * over, a refused payment leaves no file behind, and the admin can take a
 * mistaken payment back by voiding its receipt.
 */
class PaymentSettlementTest extends ReceiptTestCase
{
    private const INVOICES = '/api/workspace/invoices';

    private const ADMIN = '/api/admin/invoices';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    // ---- Review item 2: a fully credited invoice never gets stuck ----------

    public function test_a_fully_credited_invoice_cannot_be_marked_unpaid(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $invoice = $this->invoiceOf($owner, $workspace, $this->subscriber($workspace), '100.00');
        app(CreditNoteService::class)->issue($invoice->fresh(), '100.00', 'Cancelled', $owner);
        Sanctum::actingAs($this->billingAdmin());

        $this->putJson(self::ADMIN."/{$invoice->id}/mark-unpaid")
            ->assertStatus(409)
            ->assertJsonPath('message', __('messages.invoice_nothing_owed_cannot_unpaid'));

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
    }

    public function test_mark_paid_settles_a_fully_credited_invoice_that_was_forced_unpaid(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $invoice = $this->invoiceOf($owner, $workspace, $this->subscriber($workspace), '100.00');
        app(CreditNoteService::class)->issue($invoice->fresh(), '100.00', 'Cancelled', $owner);
        // What the old admin mark-unpaid left behind: nothing is owed, yet it reads pending and unpaid.
        $invoice->fresh()->forceFill(['status' => InvoiceStatus::Pending, 'paid_at' => null])->save();
        Sanctum::actingAs($owner);

        $this->putJson(self::INVOICES."/{$invoice->id}/pay")
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('message', __('messages.invoice_marked_paid'));

        $fresh = $invoice->fresh();
        $this->assertSame('paid', $fresh->payment_status->value);
        $this->assertEquals(0, $fresh->amount_paid);
        $this->assertNotNull($fresh->paid_at);
        $this->assertSame(0, Receipt::query()->count(), 'Nothing was owed, so no money was taken.');
    }

    // ---- Review item 3: every branch of approveReceipt ---------------------

    public function test_approving_a_receipt_pays_the_open_balance_with_the_members_proof(): void
    {
        [$owner, $workspace, $customer, $invoice] = $this->underReview('100.00');

        $this->putJson(self::INVOICES."/{$invoice->id}/approve-receipt")
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('message', __('messages.invoice_marked_paid'));

        $receipt = Receipt::query()->sole();
        $this->assertSame(PaymentMethod::Transfer, $receipt->method);
        $this->assertSame($invoice->receipt_path, $receipt->receipt_path);
        $this->assertNotNull($invoice->fresh()->receipt_reviewed_at);
        Notification::assertSentTo($customer, InvoicePaidNotification::class);
    }

    public function test_approving_a_receipt_while_a_cheque_holds_part_leaves_it_partially_paid(): void
    {
        [$owner, $workspace, $customer, $invoice] = $this->underReview('100.00', cheque: '40.00');

        $this->putJson(self::INVOICES."/{$invoice->id}/approve-receipt")
            ->assertOk()
            ->assertJsonPath('data.status', 'partially_paid')
            ->assertJsonPath('message', __('messages.invoice_marked_partially_paid'));

        $this->assertEquals(60, $invoice->fresh()->amount_paid);
        $this->assertNotNull($invoice->fresh()->receipt_reviewed_at);
    }

    public function test_approving_a_receipt_while_cheques_hold_everything_changes_nothing(): void
    {
        [$owner, $workspace, $customer, $invoice] = $this->underReview('100.00', cheque: '100.00');

        $this->putJson(self::INVOICES."/{$invoice->id}/approve-receipt")
            ->assertUnprocessable()
            ->assertJsonPath('message', __('messages.invoice_nothing_open_to_pay'));

        $fresh = $invoice->fresh();
        $this->assertSame(InvoiceStatus::UnderReview, $fresh->status);
        $this->assertNull($fresh->receipt_reviewed_at, 'The review stamp is written with the payment, so a refused one leaves none.');
        $this->assertSame(1, Receipt::query()->count());
    }

    public function test_approving_a_receipt_on_a_fully_credited_invoice_settles_it(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $invoice = $this->invoiceOf($owner, $workspace, $this->subscriber($workspace), '100.00');
        app(CreditNoteService::class)->issue($invoice->fresh(), '100.00', 'Cancelled', $owner);
        $invoice->fresh()->forceFill(['status' => InvoiceStatus::Pending, 'paid_at' => null])->save();
        app(InvoiceService::class)->submitReceipt($invoice->fresh(), UploadedFile::fake()->image('member.png'));
        Sanctum::actingAs($owner);

        $this->putJson(self::INVOICES."/{$invoice->id}/approve-receipt")
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $this->assertNotNull($invoice->fresh()->receipt_reviewed_at);
        $this->assertSame(0, Receipt::query()->count());
    }

    public function test_approving_needs_a_receipt_under_review(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $invoice = $this->invoiceOf($owner, $workspace, $this->subscriber($workspace), '100.00');
        Sanctum::actingAs($owner);

        $this->putJson(self::INVOICES."/{$invoice->id}/approve-receipt")
            ->assertUnprocessable()
            ->assertJsonPath('message', __('messages.invoice_receipt_not_under_review'));

        $this->assertNull($invoice->fresh()->receipt_reviewed_at);
    }

    public function test_approving_on_a_void_invoice_is_refused(): void
    {
        [$owner, $workspace, , $invoice] = $this->underReview('100.00');
        $invoice->fresh()->forceFill(['doc_status' => DocStatus::Void, 'voided_at' => now(), 'void_reason' => 'Mistake'])->save();

        $this->putJson(self::INVOICES."/{$invoice->id}/approve-receipt")->assertStatus(409);

        $this->assertSame(0, Receipt::query()->count());
    }

    public function test_approving_with_finance_v2_off_marks_paid_as_before(): void
    {
        [$owner, $workspace] = $this->owningWorkspace(financeV2: false);
        $invoice = Invoice::factory()->create([
            'subscription_id' => Subscription::factory()->create(['workspace_id' => $workspace->id])->id,
            'amount' => '50.00',
            'status' => InvoiceStatus::UnderReview,
        ]);
        Sanctum::actingAs($owner);

        $this->putJson(self::INVOICES."/{$invoice->id}/approve-receipt")->assertOk()->assertJsonPath('data.status', 'paid');

        $this->assertEquals(50, $invoice->fresh()->amount_paid);
        $this->assertNotNull($invoice->fresh()->receipt_reviewed_at);
        $this->assertSame(0, Receipt::query()->count());
    }

    // ---- Review item 4: the real status, the proof on the receipt ---------

    public function test_mark_paid_writes_a_numbered_receipt_an_audit_entry_and_tells_the_member(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        Sanctum::actingAs($owner);

        $this->putJson(self::INVOICES."/{$invoice->id}/pay")->assertOk()->assertJsonPath('data.status', 'paid');

        $receipt = Receipt::query()->sole();
        $this->assertStringContainsString('-REC-', $receipt->number);
        $this->assertSame(PaymentMethod::Cash, $receipt->method);
        $this->assertEquals(100, $receipt->amount);
        $this->assertTrue(AuditLog::query()->where('action', 'finance.receipt.created')->where('actor_id', $owner->id)->exists());
        Notification::assertSentTo($customer, InvoicePaidNotification::class);
    }

    public function test_mark_paid_reports_partially_paid_when_a_cheque_holds_part(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $this->pendingCheque($owner, $workspace, $customer, $invoice, '40.00');
        Sanctum::actingAs($owner);

        $this->putJson(self::INVOICES."/{$invoice->id}/pay")
            ->assertOk()
            ->assertJsonPath('data.status', 'partially_paid')
            ->assertJsonPath('message', __('messages.invoice_marked_partially_paid'));

        Notification::assertNotSentTo($customer, InvoicePaidNotification::class);
    }

    public function test_mark_paid_takes_an_optional_method_that_defaults_to_cash(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $card = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $cash = $this->invoiceOf($owner, $workspace, $customer, '50.00');
        Sanctum::actingAs($owner);

        $this->putJson(self::INVOICES."/{$card->id}/pay", ['method' => 'cheque'])->assertUnprocessable()->assertJsonValidationErrors('method');
        $this->putJson(self::INVOICES."/{$card->id}/pay", ['method' => 'card'])->assertOk();
        $this->putJson(self::INVOICES."/{$cash->id}/pay")->assertOk();

        $this->assertSame(PaymentMethod::Card, $this->receiptOf($card)->method);
        $this->assertSame(PaymentMethod::Cash, $this->receiptOf($cash)->method);
    }

    public function test_uploading_proof_puts_it_on_the_receipt_and_reports_partially_paid(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $this->pendingCheque($owner, $workspace, $customer, $invoice, '40.00');
        Sanctum::actingAs($owner);

        $this->post(self::INVOICES."/{$invoice->id}/receipt", ['receipt' => UploadedFile::fake()->image('p.png')], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.status', 'partially_paid')
            ->assertJsonPath('message', __('messages.invoice_marked_partially_paid'));

        $receipt = $this->receiptOf($invoice);
        $this->assertSame(PaymentMethod::Transfer, $receipt->method);
        $this->assertEquals(60, $receipt->amount);
        Storage::disk('local')->assertExists((string) $receipt->receipt_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_uploading_proof_for_the_whole_balance_writes_a_numbered_receipt_and_tells_the_member(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        Sanctum::actingAs($owner);

        $this->post(self::INVOICES."/{$invoice->id}/receipt", ['receipt' => UploadedFile::fake()->image('p.png')], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('message', __('messages.invoice_marked_paid'));

        $receipt = $this->receiptOf($invoice);
        $this->assertStringContainsString('-REC-', $receipt->number);
        $this->assertEquals(100, $receipt->amount);
        $this->assertSame($invoice->payments()->sole()->id, $receipt->legacy_payment_id);
        $this->assertTrue(AuditLog::query()->where('action', 'finance.receipt.created')->where('actor_id', $owner->id)->exists());
        Notification::assertSentTo($customer, InvoicePaidNotification::class);
    }

    public function test_a_refused_proof_payment_leaves_no_file_behind(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $this->pendingCheque($owner, $workspace, $customer, $invoice, '100.00');
        Sanctum::actingAs($owner);

        $this->post(self::INVOICES."/{$invoice->id}/receipt", ['receipt' => UploadedFile::fake()->image('p.png')], ['Accept' => 'application/json'])
            ->assertUnprocessable();

        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_admin_mark_paid_with_proof_puts_it_on_the_receipt_and_reports_partially_paid(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $this->pendingCheque($owner, $workspace, $customer, $invoice, '40.00');
        Sanctum::actingAs($this->billingAdmin());

        $response = $this->put(self::ADMIN."/{$invoice->id}/mark-paid", ['receipt' => UploadedFile::fake()->image('p.png'), 'paid_at' => now()->toDateString()], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.status', 'partially_paid')
            ->assertJsonPath('message', __('messages.invoice_marked_partially_paid'));

        $receipt = $this->receiptOf($invoice);
        $this->assertSame(PaymentMethod::Transfer, $receipt->method);
        Storage::disk('local')->assertExists((string) $receipt->receipt_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertNull($invoice->fresh()->receipt_path);
        $this->assertStringContainsString('signature=', (string) $response->json('data.payments.0.receipt_url'));
    }

    public function test_admin_mark_paid_with_proof_keeps_no_file_when_the_payment_is_refused(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $this->pendingCheque($owner, $workspace, $customer, $invoice, '100.00');
        Sanctum::actingAs($this->billingAdmin());

        $this->put(self::ADMIN."/{$invoice->id}/mark-paid", ['receipt' => UploadedFile::fake()->image('p.png')], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('message', __('messages.invoice_nothing_open_to_pay'));

        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertNull($invoice->fresh()->receipt_path);
    }

    public function test_admin_attach_receipt_with_nothing_open_is_refused_and_keeps_no_file(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $this->pendingCheque($owner, $workspace, $customer, $invoice, '100.00');
        Sanctum::actingAs($this->billingAdmin());

        $this->post(self::ADMIN."/{$invoice->id}/receipt", ['receipt' => UploadedFile::fake()->image('p.png')], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('message', __('messages.invoice_nothing_open_to_pay'));

        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertNull($invoice->fresh()->receipt_path);
        $this->assertEquals(0, $invoice->fresh()->amount_paid);
    }

    public function test_admin_attach_receipt_reports_partially_paid(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $this->pendingCheque($owner, $workspace, $customer, $invoice, '40.00');
        Sanctum::actingAs($this->billingAdmin());

        $this->post(self::ADMIN."/{$invoice->id}/receipt", ['receipt' => UploadedFile::fake()->image('p.png')], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.status', 'partially_paid')
            ->assertJsonPath('message', __('messages.invoice_marked_partially_paid'));

        $this->assertEquals(60, $invoice->fresh()->amount_paid);
    }

    public function test_admin_attach_receipt_on_a_fully_credited_invoice_settles_it(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $invoice = $this->invoiceOf($owner, $workspace, $this->subscriber($workspace), '100.00');
        app(CreditNoteService::class)->issue($invoice->fresh(), '100.00', 'Cancelled', $owner);
        $invoice->fresh()->forceFill(['status' => InvoiceStatus::Pending, 'paid_at' => null])->save();
        Sanctum::actingAs($this->billingAdmin());

        $this->post(self::ADMIN."/{$invoice->id}/receipt", ['receipt' => UploadedFile::fake()->image('p.png')], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $this->assertSame(0, Receipt::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles(), 'Nothing was paid, so no receipt keeps the proof.');
    }

    // ---- Review item 5: the admin can undo a mistaken mark-paid ------------

    public function test_admin_mark_unpaid_voids_the_receipt_that_paid_the_invoice(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $invoice = $this->invoiceOf($owner, $workspace, $this->subscriber($workspace), '100.00');
        Sanctum::actingAs($owner);
        $this->putJson(self::INVOICES."/{$invoice->id}/pay")->assertOk();
        $admin = $this->billingAdmin();
        Sanctum::actingAs($admin);

        $this->putJson(self::ADMIN."/{$invoice->id}/mark-unpaid")->assertOk()->assertJsonPath('data.status', 'pending');

        $fresh = $invoice->fresh();
        $this->assertSame('unpaid', $fresh->payment_status->value);
        $this->assertEquals(0, $fresh->amount_paid);
        $this->assertNull($fresh->paid_at);
        $receipt = Receipt::query()->sole();
        $this->assertNotNull($receipt->voided_at);
        $this->assertSame(__('messages.invoice_unpaid_receipt_void_reason'), $receipt->void_reason);
        $this->assertSame(0, InvoicePayment::query()->count());
        $this->assertTrue(AuditLog::query()->where('action', 'finance.receipt.voided')->where('actor_id', $admin->id)->exists());
    }

    public function test_admin_mark_unpaid_refuses_a_receipt_that_also_pays_another_invoice(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $first = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $second = $this->invoiceOf($owner, $workspace, $customer, '50.00');
        app(ReceiptService::class)->create($workspace, $this->receiptPayload($customer, '150.00', [
            'allocations' => [...$this->allocation($first, '100.00'), ...$this->allocation($second, '50.00')],
        ]), $owner);
        Sanctum::actingAs($this->billingAdmin());

        $this->putJson(self::ADMIN."/{$first->id}/mark-unpaid")
            ->assertStatus(409)
            ->assertJsonPath('message', __('messages.receipt_shared_allocation'));

        $this->assertNull(Receipt::query()->sole()->voided_at);
        $this->assertEquals(100, $first->fresh()->amount_paid);
        $this->assertEquals(50, $second->fresh()->amount_paid);
    }

    public function test_admin_mark_unpaid_leaves_a_pending_cheque_alone(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $cheque = $this->pendingCheque($owner, $workspace, $customer, $invoice, '40.00');
        Sanctum::actingAs($owner);
        $this->putJson(self::INVOICES."/{$invoice->id}/pay")->assertOk();
        Sanctum::actingAs($this->billingAdmin());

        $this->putJson(self::ADMIN."/{$invoice->id}/mark-unpaid")->assertOk();

        $this->assertNull($cheque->fresh()->voided_at, 'The cheque is real money still to clear, not part of the mark-paid.');
        $this->assertEquals(0, $invoice->fresh()->amount_paid);
        $this->assertSame('unpaid', $invoice->fresh()->payment_status->value);
    }

    public function test_admin_mark_unpaid_clears_a_payment_recorded_before_finance_v2(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $invoice = $this->invoiceOf($owner, $workspace, $this->subscriber($workspace), '100.00');
        // Marked paid while the flag was still off: money on the invoice, no receipt and no payment row.
        $invoice->fresh()->forceFill(['status' => InvoiceStatus::Paid, 'amount_paid' => '100.00', 'paid_at' => now()])->save();
        $admin = $this->billingAdmin();
        Sanctum::actingAs($admin);

        $this->putJson(self::ADMIN."/{$invoice->id}/mark-unpaid")->assertOk()->assertJsonPath('data.status', 'pending');

        $this->assertEquals(0, $invoice->fresh()->amount_paid);
        $entry = AuditLog::query()->where('action', 'finance.invoice.marked_unpaid')->sole();
        $this->assertSame($admin->id, $entry->actor_id);
        $this->assertSame('100.00', $entry->changes['cleared_without_receipt']);
    }

    public function test_admin_mark_unpaid_refuses_payments_not_yet_copied_into_receipts(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $invoice = $this->invoiceOf($owner, $workspace, $this->subscriber($workspace), '100.00');
        $invoice->payments()->create(['amount' => '40.00', 'paid_at' => now()]);
        $invoice->fresh()->forceFill(['status' => InvoiceStatus::PartiallyPaid, 'amount_paid' => '40.00'])->save();
        Sanctum::actingAs($this->billingAdmin());

        $this->putJson(self::ADMIN."/{$invoice->id}/mark-unpaid")
            ->assertStatus(409)
            ->assertJsonPath('message', __('messages.invoice_unpaid_legacy_payments'));

        $this->assertEquals(40, $invoice->fresh()->amount_paid);
        $this->assertSame(1, InvoicePayment::query()->count());
    }

    // ---- Helpers ------------------------------------------------------------

    /**
     * An issued invoice whose member has submitted proof, the owner signed in.
     *
     * @return array{0: User, 1: Workspace, 2: User, 3: Invoice}
     */
    private function underReview(string $amount, ?string $cheque = null): array
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, $amount);

        if ($cheque !== null) {
            $this->pendingCheque($owner, $workspace, $customer, $invoice, $cheque);
        }

        app(InvoiceService::class)->submitReceipt($invoice->fresh(), UploadedFile::fake()->image('member.png'));
        Sanctum::actingAs($owner);

        return [$owner, $workspace, $customer, $invoice->fresh()];
    }

    private function billingAdmin(): User
    {
        $admin = User::factory()->admin()->create();
        Permission::findOrCreate('manage_billing', 'web');
        $admin->givePermissionTo('manage_billing');

        return $admin;
    }

    /** The single live, non-cheque receipt allocated to the invoice. */
    private function receiptOf(Invoice $invoice): Receipt
    {
        return Receipt::query()
            ->where('method', '!=', PaymentMethod::Cheque->value)
            ->whereHas('allocations', static fn ($query) => $query->where('invoice_id', $invoice->id))
            ->sole();
    }
}
