<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\WalletEntryType;
use App\Models\AuditLog;
use App\Models\WalletEntry;
use App\Services\Finance\CreditNoteService;
use App\Services\Finance\InvoiceRefundWallet;
use App\Services\Finance\ReceiptService;
use App\Services\Finance\WalletLedger;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/** The part of a credit the customer had already paid lands on their wallet, once. */
class CreditNoteWalletTest extends WalletTestCase
{
    public function test_a_credit_over_the_paid_part_moves_what_is_owed_back_to_the_wallet(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        Sanctum::actingAs($owner);
        $this->twoFactor($owner);
        $this->postJson(self::RECEIPTS, $this->receiptPayload($customer, '60.00', ['allocations' => $this->allocation($invoice, '60.00')]))->assertCreated();

        $this->postJson("/api/workspace/invoices/{$invoice->id}/credit-notes", ['amount' => '100.00', 'reason' => 'Service cancelled'])->assertCreated();

        $entry = WalletEntry::query()->firstOrFail();
        $this->assertSame(WalletEntryType::Credit, $entry->type);
        $this->assertEquals(60, $entry->amount);
        $this->assertSame(WalletLedger::RELATED_INVOICE, $entry->related_type);
        $this->assertSame($invoice->id, $entry->related_id);
        $this->assertSame($customer->id, $entry->customer_id);
        $this->assertSame('60.00', $this->balanceOf($workspace, $customer));
        $this->assertWalletInStep($invoice, '60.00');
        $this->assertTrue(AuditLog::query()->where('action', 'finance.wallet.refund_credited')->exists());
    }

    public function test_the_invoice_shows_what_is_owed_back_apart_from_what_is_on_the_wallet(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        app(ReceiptService::class)->create($workspace, $this->receiptPayload($customer, '100.00', ['allocations' => $this->allocation($invoice, '100.00')]), $owner);
        app(CreditNoteService::class)->issue($invoice->fresh(), '40.00', 'Discount', $owner);
        $this->actAsOwner($owner);

        $this->getJson("/api/workspace/invoices/{$invoice->id}")->assertOk()
            ->assertJsonPath('data.refund_due', '40.00')
            ->assertJsonPath('data.moved_to_wallet', '40.00')
            // The same money is on the wallet now, so it is not shown as still owed back as well.
            ->assertJsonPath('data.refund_open', '0.00');
    }

    public function test_a_credit_on_an_unpaid_invoice_leaves_the_wallet_alone(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        Sanctum::actingAs($owner);
        $this->twoFactor($owner);

        $this->postJson("/api/workspace/invoices/{$invoice->id}/credit-notes", ['amount' => '100.00', 'reason' => 'Billed in error'])->assertCreated();

        $this->assertSame(0, WalletEntry::query()->count());
        $this->assertSame(0, DB::table('customer_wallets')->count());
    }

    public function test_two_credit_notes_on_one_paid_invoice_move_thirty_then_twenty_not_eighty(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        app(ReceiptService::class)->create($workspace, $this->receiptPayload($customer, '100.00', ['allocations' => $this->allocation($invoice, '100.00')]), $owner);
        $notes = app(CreditNoteService::class);

        $notes->issue($invoice->fresh(), '30.00', 'First', $owner);
        $notes->issue($invoice->fresh(), '20.00', 'Second', $owner);

        $this->assertSame(['30.00', '20.00'], WalletEntry::query()->orderBy('created_at')->orderBy('amount', 'desc')->pluck('amount')->map(fn ($a): string => (string) $a)->all());
        $this->assertSame('50.00', $this->balanceOf($workspace, $customer));
        $this->assertWalletInStep($invoice, '50.00');
    }

    public function test_running_the_sync_again_posts_nothing(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        app(ReceiptService::class)->create($workspace, $this->receiptPayload($customer, '100.00', ['allocations' => $this->allocation($invoice, '100.00')]), $owner);
        app(CreditNoteService::class)->issue($invoice->fresh(), '25.00', 'Discount', $owner);

        DB::transaction(fn () => app(InvoiceRefundWallet::class)->sync($invoice->fresh(), $owner));
        DB::transaction(fn () => app(InvoiceRefundWallet::class)->sync($invoice->fresh(), $owner));

        $this->assertSame(1, WalletEntry::query()->count());
        $this->assertWalletInStep($invoice, '25.00');
    }

    public function test_the_wallet_credit_can_be_paid_back_with_a_voucher(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        Sanctum::actingAs($owner);
        $this->twoFactor($owner);
        $this->postJson(self::RECEIPTS, $this->receiptPayload($customer, '100.00', ['allocations' => $this->allocation($invoice, '100.00')]))->assertCreated();
        $this->postJson("/api/workspace/invoices/{$invoice->id}/credit-notes", ['amount' => '25.00', 'reason' => 'Partial refund'])->assertCreated();

        $this->postJson($this->walletUrl($customer, 'returns'), ['amount' => '25.00', 'method' => 'cash', 'reason' => 'Credit note refund'])->assertCreated();
        $this->postJson($this->walletUrl($customer, 'returns'), ['amount' => '0.01', 'method' => 'cash', 'reason' => 'Once more'])->assertUnprocessable();

        $this->assertSame('0.00', $this->balanceOf($workspace, $customer));
    }
}
