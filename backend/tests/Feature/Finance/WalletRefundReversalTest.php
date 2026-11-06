<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\ChequeStatus;
use App\Enums\PaymentMethod;
use App\Enums\WalletEntryType;
use App\Exceptions\WalletException;
use App\Models\AuditLog;
use App\Models\Receipt;
use App\Models\WalletEntry;
use App\Services\Finance\CreditNoteService;
use App\Services\Finance\InvoiceBalanceService;
use App\Services\Finance\PaymentReversalService;
use App\Services\Finance\ReceiptService;
use App\Services\Finance\WalletLedger;
use App\Services\InvoiceService;

/**
 * Money a credit note moved to the wallet comes back off it when the payment
 * behind it goes away (receipt void, cheque bounce, mark-unpaid), and the
 * wallet never goes negative: a change that would need money the customer
 * already took out is refused and rolled back.
 */
class WalletRefundReversalTest extends WalletTestCase
{
    /** P1 (B2): deposit 100, settle 100, credit 30, void the wallet receipt: the wallet ends at 100, not 130. */
    public function test_voiding_a_wallet_receipt_after_a_credit_leaves_the_deposit_and_the_invoice_owing_the_rest(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $this->deposit($workspace, $customer, '100.00', $owner);
        $receipt = $this->service()->settleAgainstInvoice($workspace, $customer->id, $invoice->id, '100.00', null, $owner);
        app(CreditNoteService::class)->issue($invoice->fresh(), '30.00', 'Discount', $owner);
        $this->assertSame('30.00', $this->balanceOf($workspace, $customer));

        app(ReceiptService::class)->void($receipt->fresh(), 'Paid by mistake', $owner);

        $this->assertSame('100.00', $this->balanceOf($workspace, $customer));
        $this->assertSame('70.00', app(InvoiceBalanceService::class)->unpaid($invoice->fresh()));
        $this->assertWalletInStep($invoice, '0.00');
    }

    /** P2: cash 60, credit 70 (wallet 30), void the cash receipt: the 30 leaves the wallet, so no voucher can pay it out. */
    public function test_voiding_the_cash_receipt_behind_a_wallet_credit_takes_it_back_off_the_wallet(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $cash = app(ReceiptService::class)->create($workspace, $this->receiptPayload($customer, '60.00', ['allocations' => $this->allocation($invoice, '60.00')]), $owner);
        app(CreditNoteService::class)->issue($invoice->fresh(), '70.00', 'Discount', $owner);
        $this->assertSame('30.00', $this->balanceOf($workspace, $customer));

        app(ReceiptService::class)->void($cash->fresh(), 'Wrong customer', $owner);

        $this->assertSame('0.00', $this->balanceOf($workspace, $customer));
        $this->assertSame(1, WalletEntry::query()->where('type', WalletEntryType::Debit)->where('related_id', $invoice->id)->count());
        $this->assertWalletInStep($invoice, '0.00');
        $this->expectException(WalletException::class);
        $this->giveBack($workspace, $customer, '30.00', $owner);
    }

    /** P3: cheque 100 cleared, credit 100 (wallet 100), the cheque bounces: no 100 can be paid out any more. */
    public function test_a_bounced_cheque_takes_its_refund_back_off_the_wallet(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $cheque = $this->clearedCheque($owner, $workspace, $customer, $invoice, '100.00');
        app(CreditNoteService::class)->issue($invoice->fresh(), '100.00', 'Cancelled', $owner);
        $this->assertSame('100.00', $this->balanceOf($workspace, $customer));

        app(ReceiptService::class)->changeChequeStatus($cheque->fresh(), ChequeStatus::Bounced, $owner);

        $this->assertSame('0.00', $this->balanceOf($workspace, $customer));
        $this->assertWalletInStep($invoice, '0.00');
        $this->assertTrue(AuditLog::query()->where('action', 'finance.wallet.refund_debited')->exists());
        $this->expectException(WalletException::class);
        $this->giveBack($workspace, $customer, '100.00', $owner);
    }

    public function test_a_void_that_needs_money_already_paid_out_is_refused_with_a_409_naming_the_voucher(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $cash = app(ReceiptService::class)->create($workspace, $this->receiptPayload($customer, '60.00', ['allocations' => $this->allocation($invoice, '60.00')]), $owner);
        app(CreditNoteService::class)->issue($invoice->fresh(), '70.00', 'Discount', $owner);
        $voucher = $this->giveBack($workspace, $customer, '30.00', $owner);
        $this->actAsOwner($owner);

        $response = $this->postJson(self::RECEIPTS."/{$cash->id}/void", ['reason' => 'Wrong customer']);

        $response->assertStatus(409)->assertJsonPath('code', 'wallet_refund_spent');
        $this->assertStringContainsString($voucher->number, (string) $response->json('message'));
        // Rolled back: the receipt is live, the invoice keeps its payment, the wallet is untouched.
        $this->assertNull($cash->fresh()->voided_at);
        $this->assertEquals(60, $invoice->fresh()->amount_paid);
        $this->assertSame('0.00', $this->balanceOf($workspace, $customer));
        $this->assertWalletInStep($invoice, '30.00');
    }

    public function test_the_refusal_is_in_arabic_for_an_arabic_request(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $cash = app(ReceiptService::class)->create($workspace, $this->receiptPayload($customer, '60.00', ['allocations' => $this->allocation($invoice, '60.00')]), $owner);
        app(CreditNoteService::class)->issue($invoice->fresh(), '70.00', 'Discount', $owner);
        $this->service()->forfeit($workspace, $customer->id, '30.00', 'Kept for damage', null, $owner);
        $this->actAsOwner($owner);

        $response = $this->postJson(self::RECEIPTS."/{$cash->id}/void", ['reason' => 'Wrong customer'], ['Accept-Language' => 'ar']);

        $response->assertStatus(409)->assertJsonPath('code', 'wallet_refund_spent');
        $this->assertSame(__('messages.wallet_refund_spent_unnamed', ['amount' => '30.00', 'balance' => '0.00'], 'ar'), $response->json('message'));
    }

    public function test_a_bounce_that_needs_money_already_spent_on_another_invoice_is_refused_and_the_cheque_stays_cleared(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $other = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $cheque = $this->clearedCheque($owner, $workspace, $customer, $invoice, '100.00');
        app(CreditNoteService::class)->issue($invoice->fresh(), '100.00', 'Cancelled', $owner);
        $settled = $this->service()->settleAgainstInvoice($workspace, $customer->id, $other->id, '100.00', null, $owner);

        try {
            app(ReceiptService::class)->changeChequeStatus($cheque->fresh(), ChequeStatus::Bounced, $owner);
            $this->fail('The bounce needs money the wallet no longer holds.');
        } catch (WalletException $e) {
            $this->assertStringContainsString((string) $settled->number, $e->getMessage());
        }

        $this->assertSame(ChequeStatus::Cleared, $cheque->fresh()->cheque_status);
        $this->assertEquals(100, $invoice->fresh()->amount_paid);
        $this->assertWalletInStep($invoice, '100.00');
    }

    public function test_marking_an_invoice_unpaid_takes_its_refund_back_off_the_wallet(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        app(InvoiceService::class)->markPaid($invoice->fresh(), null, PaymentMethod::Cash);
        app(CreditNoteService::class)->issue($invoice->fresh(), '30.00', 'Discount', $owner);
        $this->assertSame('30.00', $this->balanceOf($workspace, $customer));

        app(PaymentReversalService::class)->reverse($invoice->fresh(), $owner);

        $this->assertSame('0.00', $this->balanceOf($workspace, $customer));
        $this->assertEquals(0, $invoice->fresh()->amount_paid);
        $this->assertWalletInStep($invoice, '0.00');
    }

    public function test_marking_unpaid_is_refused_when_the_refund_was_already_paid_out(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        app(InvoiceService::class)->markPaid($invoice->fresh(), null, PaymentMethod::Cash);
        app(CreditNoteService::class)->issue($invoice->fresh(), '30.00', 'Discount', $owner);
        $this->giveBack($workspace, $customer, '30.00', $owner);

        try {
            app(PaymentReversalService::class)->reverse($invoice->fresh(), $owner);
            $this->fail('The refund was paid out already.');
        } catch (WalletException) {
            // expected
        }

        $this->assertEquals(100, $invoice->fresh()->amount_paid);
        $this->assertSame(1, Receipt::query()->whereNull('voided_at')->count());
        $this->assertWalletInStep($invoice, '30.00');
    }

    public function test_voiding_the_voucher_first_lets_the_void_go_through(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $cash = app(ReceiptService::class)->create($workspace, $this->receiptPayload($customer, '60.00', ['allocations' => $this->allocation($invoice, '60.00')]), $owner);
        app(CreditNoteService::class)->issue($invoice->fresh(), '70.00', 'Discount', $owner);
        $voucher = $this->giveBack($workspace, $customer, '30.00', $owner);

        app(\App\Services\Finance\PaymentVoucherService::class)->void($voucher, 'Not handed over', $owner);
        app(ReceiptService::class)->void($cash->fresh(), 'Wrong customer', $owner);

        $this->assertSame('0.00', $this->balanceOf($workspace, $customer));
        $this->assertWalletInStep($invoice, '0.00');
        $this->assertSame(1, WalletEntry::query()->where('related_type', WalletLedger::RELATED_VOUCHER)->count());
    }
}
