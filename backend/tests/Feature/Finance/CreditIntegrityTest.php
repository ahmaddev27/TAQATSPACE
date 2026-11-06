<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\ChequeStatus;
use App\Enums\DocStatus;
use App\Enums\InvoiceStatus;
use App\Exceptions\InvoiceLifecycleException;
use App\Exceptions\ReceiptException;
use App\Http\Resources\InvoiceResource;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Admin\AdminInvoiceService;
use App\Services\Auth\TwoFactorService;
use App\Services\Finance\CreditNoteService;
use App\Services\Finance\InvoiceBalanceService;
use App\Services\Finance\InvoiceLifecycleService;
use App\Services\Finance\ReceiptService;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use RuntimeException;

/**
 * Money-integrity fixes around credit notes, cheques and the legacy payment
 * paths. Each test names the defect it pins down.
 */
class CreditIntegrityTest extends ReceiptTestCase
{
    // ---- Defect 1: the amount owed back is derived, never stored ----------

    public function test_the_refund_owed_back_drops_to_zero_when_a_cleared_cheque_bounces(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $cheque = $this->clearedCheque($owner, $workspace, $customer, $invoice, '100.00');
        app(CreditNoteService::class)->issue($invoice->fresh(), '100.00', 'Cancelled', $owner);
        $this->actAsOwner($owner);

        $this->assertEquals(100, $this->listedRefund($invoice));

        app(ReceiptService::class)->changeChequeStatus($cheque->fresh(), ChequeStatus::Bounced, $owner);

        $this->assertEquals(0, $this->listedRefund($invoice));
        $this->assertEquals(0, app(InvoiceBalanceService::class)->refundDue($invoice->fresh()));
    }

    public function test_the_refund_owed_back_drops_when_the_receipt_is_voided(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $receipt = app(ReceiptService::class)->create($workspace, $this->receiptPayload($customer, '60.00', [
            'allocations' => $this->allocation($invoice, '60.00'),
        ]), $owner);
        app(CreditNoteService::class)->issue($invoice->fresh(), '70.00', 'Discount', $owner);
        $this->actAsOwner($owner);

        $this->assertEquals(30, $this->listedRefund($invoice));

        app(ReceiptService::class)->void($receipt->fresh(), 'Wrong customer', $owner);

        $this->assertEquals(0, $this->listedRefund($invoice));
        $this->assertEquals(30, app(InvoiceBalanceService::class)->unpaid($invoice->fresh()));
    }

    public function test_refund_due_is_the_paid_amount_above_what_is_still_payable(): void
    {
        $service = app(InvoiceBalanceService::class);
        $invoice = new Invoice(['amount' => '100.00', 'credited_amount' => '30.00', 'amount_paid' => '85.50']);

        $this->assertSame('15.50', $service->refundDue($invoice));
        $this->assertSame('0.00', $service->refundDue(new Invoice(['amount' => '100.00', 'credited_amount' => '0.00', 'amount_paid' => '100.00'])));
        $this->assertSame('0.00', $service->refundDue(new Invoice(['amount' => '100.00', 'credited_amount' => '0.00', 'amount_paid' => '20.00'])));
    }

    // ---- Defect 2: legacy payment actions respect pending cheques ---------

    public function test_mark_paid_only_pays_what_a_pending_cheque_does_not_hold(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $cheque = $this->pendingCheque($owner, $workspace, $customer, $invoice, '40.00');

        app(InvoiceService::class)->markPaid($invoice->fresh());

        $this->assertEquals(60, $invoice->fresh()->amount_paid);
        $this->assertSame('partial', $invoice->fresh()->payment_status->value);

        $rs = app(ReceiptService::class);
        $rs->changeChequeStatus($cheque->fresh(), ChequeStatus::Deposited, $owner);
        $rs->changeChequeStatus($cheque->fresh(), ChequeStatus::Cleared, $owner);

        $this->assertEquals(100, $invoice->fresh()->amount_paid);
        $this->assertSame('paid', $invoice->fresh()->payment_status->value);
        $this->assertSame('0.00', app(InvoiceBalanceService::class)->refundDue($invoice->fresh()));
    }

    public function test_mark_paid_is_refused_when_pending_cheques_hold_the_whole_balance(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $this->pendingCheque($owner, $workspace, $customer, $invoice, '100.00');

        try {
            app(InvoiceService::class)->markPaid($invoice->fresh());
            $this->fail('Nothing is open to pay, so mark-paid must be refused.');
        } catch (ReceiptException) {
            $this->assertEquals(0, $invoice->fresh()->amount_paid);
        }
    }

    public function test_recording_a_payment_with_proof_only_pays_the_open_balance(): void
    {
        Storage::fake('local');
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $this->pendingCheque($owner, $workspace, $customer, $invoice, '40.00');
        Sanctum::actingAs($owner);

        app(InvoiceService::class)->recordPaymentWithReceipt($invoice->fresh(), UploadedFile::fake()->image('p.png'));

        $this->assertEquals(60, $invoice->fresh()->amount_paid);
        $this->assertSame(2, Receipt::query()->count());
    }

    public function test_a_clearing_cheque_that_overpays_shows_the_overage_as_owed_back(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $cheque = $this->pendingCheque($owner, $workspace, $customer, $invoice, '40.00');
        // Data that predates the fix: the invoice was marked paid in full around the cheque.
        $invoice->forceFill(['amount_paid' => '100.00'])->save();

        $rs = app(ReceiptService::class);
        $rs->changeChequeStatus($cheque->fresh(), ChequeStatus::Deposited, $owner);
        $rs->changeChequeStatus($cheque->fresh(), ChequeStatus::Cleared, $owner);

        $this->assertEquals(140, $invoice->fresh()->amount_paid);
        $this->assertSame('40.00', app(InvoiceBalanceService::class)->refundDue($invoice->fresh()));
    }

    // ---- Defect 7: void invoices -------------------------------------------

    public function test_an_invoice_with_credit_notes_cannot_be_voided(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $invoice = $this->invoiceOf($owner, $workspace, $this->subscriber($workspace), '100.00');
        app(CreditNoteService::class)->issue($invoice->fresh(), '10.00', 'Discount', $owner);

        try {
            app(InvoiceLifecycleService::class)->void($invoice->fresh(), 'Mistake', $owner);
            $this->fail('Voiding an invoice with a credit note must be refused.');
        } catch (InvoiceLifecycleException $e) {
            $this->assertSame(409, $e->render()->getStatusCode());
        }

        $this->assertSame(DocStatus::Issued, $invoice->fresh()->doc_status);
    }

    public function test_an_invoice_with_a_pending_cheque_cannot_be_voided(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $this->pendingCheque($owner, $workspace, $customer, $invoice, '40.00');

        try {
            app(InvoiceLifecycleService::class)->void($invoice->fresh(), 'Mistake', $owner);
            $this->fail('Voiding an invoice with a pending cheque must be refused.');
        } catch (InvoiceLifecycleException $e) {
            $this->assertSame(409, $e->render()->getStatusCode());
        }

        $this->assertSame(DocStatus::Issued, $invoice->fresh()->doc_status);
    }

    public function test_the_refusal_messages_exist_in_both_languages(): void
    {
        $keys = ['invoice_void_has_credit_notes', 'invoice_void_has_pending_cheques', 'invoice_has_receipts', 'invoice_has_credit_notes', 'invoice_nothing_open_to_pay', 'credit_note_request_key_reused', 'invoice_marked_partially_paid', 'invoice_nothing_owed_cannot_unpaid', 'receipt_shared_allocation', 'invoice_unpaid_receipt_void_reason'];

        foreach ($keys as $key) {
            foreach (['en', 'ar'] as $locale) {
                $this->assertNotSame("messages.{$key}", __("messages.{$key}", [], $locale), "{$key} missing in {$locale}");
            }
        }
    }

    public function test_a_cheque_cleared_after_the_invoice_was_voided_is_not_applied(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $cheque = $this->pendingCheque($owner, $workspace, $customer, $invoice, '40.00');
        // Voided before the guard existed, so a cheque is still pending against it.
        $invoice->forceFill(['doc_status' => DocStatus::Void, 'voided_at' => now(), 'void_reason' => 'Old'])->save();

        $rs = app(ReceiptService::class);
        $rs->changeChequeStatus($cheque->fresh(), ChequeStatus::Deposited, $owner);

        try {
            $rs->changeChequeStatus($cheque->fresh(), ChequeStatus::Cleared, $owner);
            $this->fail('Clearing a cheque against a void invoice must be refused.');
        } catch (InvoiceLifecycleException) {
            // Expected: the cheque stays deposited and the invoice untouched.
        }

        $this->assertEquals(0, $invoice->fresh()->amount_paid);
        $this->assertSame(ChequeStatus::Deposited, $cheque->fresh()->cheque_status);
    }

    public function test_a_void_invoice_has_nothing_remaining(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $invoice = $this->invoiceOf($owner, $workspace, $this->subscriber($workspace), '100.00');
        app(InvoiceLifecycleService::class)->void($invoice->fresh(), 'Mistake', $owner);

        $data = (new InvoiceResource($invoice->fresh()))->toArray(Request::create('/'));

        $this->assertSame('0.00', $data['remaining_amount']);
    }

    public function test_a_cancelled_legacy_invoice_has_nothing_remaining(): void
    {
        $invoice = $this->legacyInvoice('50.00');
        $invoice->forceFill(['status' => InvoiceStatus::Cancelled])->save();

        $data = (new InvoiceResource($invoice->fresh()))->toArray(Request::create('/'));

        $this->assertSame(DocStatus::Void, $invoice->fresh()->doc_status);
        $this->assertSame('0.00', $data['remaining_amount']);
    }

    // ---- Defect 8 -----------------------------------------------------------

    /** Known issue, kept as dev has it: the payment rows stay although the invoice reads unpaid. */
    public function test_mark_unpaid_with_finance_v2_off_behaves_exactly_as_before(): void
    {
        $invoice = $this->legacyInvoice('50.00');
        app(InvoiceService::class)->recordPartialPayment($invoice->fresh(), 20.0);
        app(InvoiceService::class)->markPaid($invoice->fresh());

        app(AdminInvoiceService::class)->markUnpaid($invoice->fresh());

        $fresh = $invoice->fresh();
        $this->assertSame(InvoiceStatus::Pending, $fresh->status);
        $this->assertNull($fresh->paid_at);
        $this->assertEquals(50, $fresh->amount_paid);
        $this->assertSame(1, $fresh->payments()->count());
    }

    public function test_the_credit_note_migrations_run_down_and_up_on_data(): void
    {
        $this->assertTrue(Schema::hasColumn('credit_notes', 'request_key'));
        $this->assertFalse(Schema::hasColumn('credit_notes', 'refund_amount'));

        $keyMigration = require database_path('migrations/2026_11_10_100001_add_request_key_to_credit_notes.php');
        $dropMigration = require database_path('migrations/2026_11_10_100000_drop_refund_amount_from_credit_notes.php');

        $keyMigration->down();
        $dropMigration->down();
        $this->assertFalse(Schema::hasColumn('credit_notes', 'request_key'));
        $this->assertTrue(Schema::hasColumn('credit_notes', 'refund_amount'));

        $dropMigration->up();
        $keyMigration->up();
        $dropMigration->up(); // safe to run twice
        $this->assertTrue(Schema::hasColumn('credit_notes', 'request_key'));
        $this->assertFalse(Schema::hasColumn('credit_notes', 'refund_amount'));
    }

    public function test_the_flag_off_partial_payments_add_up_exactly(): void
    {
        $invoice = $this->legacyInvoice('0.80');
        $service = app(InvoiceService::class);

        $service->recordPartialPayment($invoice->fresh(), 0.1);
        $service->recordPartialPayment($invoice->fresh(), 0.7);

        $fresh = $invoice->fresh();
        $this->assertSame(InvoiceStatus::Paid, $fresh->status);
        $this->assertEquals('0.80', $fresh->amount_paid);
    }

    public function test_the_flag_off_proof_payment_adds_up_exactly(): void
    {
        Storage::fake('public');
        $invoice = $this->legacyInvoice('0.80');
        $service = app(InvoiceService::class);

        $service->recordPayment($invoice->fresh(), 0.1, UploadedFile::fake()->image('a.png'));
        $service->recordPayment($invoice->fresh(), 0.7, UploadedFile::fake()->image('b.png'));

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
    }

    public function test_an_invoice_with_credit_notes_is_not_deleted_with_finance_v2_off(): void
    {
        $invoice = $this->legacyInvoice('50.00');
        CreditNote::query()->create([
            'workspace_id' => $invoice->workspace_id,
            'invoice_id' => $invoice->id,
            'number' => 'CN-1',
            'amount' => '5.00',
            'reason' => 'Legacy',
            'issued_at' => now(),
        ]);

        try {
            app(InvoiceService::class)->delete($invoice->fresh());
            $this->fail('Deleting an invoice with credit notes must be refused.');
        } catch (InvoiceLifecycleException $e) {
            $this->assertSame(409, $e->render()->getStatusCode());
        }

        $this->assertSame(1, CreditNote::query()->count());
        $this->assertNotNull(Invoice::query()->find($invoice->id));
    }

    public function test_an_invoice_without_credit_notes_is_still_deleted_with_finance_v2_off(): void
    {
        $invoice = $this->legacyInvoice('50.00');

        app(InvoiceService::class)->delete($invoice->fresh());

        $this->assertNull(Invoice::query()->find($invoice->id));
    }

    public function test_mark_paid_with_finance_v2_off_still_pays_the_net_payable_in_full(): void
    {
        $invoice = $this->legacyInvoice('50.00');

        app(InvoiceService::class)->markPaid($invoice->fresh());

        $this->assertEquals(50, $invoice->fresh()->amount_paid);
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertThrows(fn () => app(InvoiceService::class)->markPaid($invoice->fresh()), RuntimeException::class);
    }

    // ---- Helpers ------------------------------------------------------------

    /** An invoice of a workspace that is still on the legacy payment paths. */
    private function legacyInvoice(string $amount): Invoice
    {
        [, $workspace] = $this->owningWorkspace(financeV2: false);
        $subscription = Subscription::factory()->create(['workspace_id' => $workspace->id]);

        return Invoice::factory()->create(['subscription_id' => $subscription->id, 'amount' => $amount, 'amount_paid' => '0.00']);
    }

    private function pendingCheque(User $owner, Workspace $workspace, User $customer, Invoice $invoice, string $amount): Receipt
    {
        return app(ReceiptService::class)->create($workspace, $this->receiptPayload($customer, $amount, [
            'method' => 'cheque',
            'allocations' => $this->allocation($invoice, $amount),
        ]), $owner);
    }

    private function clearedCheque(User $owner, Workspace $workspace, User $customer, Invoice $invoice, string $amount): Receipt
    {
        $service = app(ReceiptService::class);
        $cheque = $this->pendingCheque($owner, $workspace, $customer, $invoice, $amount);
        $service->changeChequeStatus($cheque, ChequeStatus::Deposited, $owner);

        return $service->changeChequeStatus($cheque->fresh(), ChequeStatus::Cleared, $owner);
    }

    private function actAsOwner(User $owner): void
    {
        Sanctum::actingAs($owner);
        $owner->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()])->save();
        app(TwoFactorService::class)->markVerified($owner);
    }

    /** The amount owed back, as the invoice page reports it. */
    private function listedRefund(Invoice $invoice): mixed
    {
        return $this->getJson("/api/workspace/invoices/{$invoice->id}")->assertOk()->json('data.refund_due');
    }
}
