<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\WalletEntryType;
use App\Models\AuditLog;
use App\Models\CreditNote;
use App\Models\WalletEntry;
use App\Services\Finance\WalletLedger;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/** The part of a credit the customer had already paid lands on their wallet. */
class CreditNoteWalletTest extends WalletTestCase
{
    public function test_a_credit_over_the_unpaid_part_credits_the_wallet_with_the_paid_part(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $this->twoFactor($owner);
        Sanctum::actingAs($owner);
        $this->postJson(self::RECEIPTS, $this->receiptPayload($customer, '60.00', ['allocations' => $this->allocation($invoice, '60.00')]))->assertCreated();

        $this->postJson("/api/workspace/invoices/{$invoice->id}/credit-notes", ['amount' => '100.00', 'reason' => 'Service cancelled'])->assertCreated();

        $note = CreditNote::query()->firstOrFail();
        $entry = WalletEntry::query()->firstOrFail();
        $this->assertEquals(60, $note->refund_amount);
        $this->assertSame(WalletEntryType::Credit, $entry->type);
        $this->assertEquals(60, $entry->amount);
        $this->assertSame($note->id, $entry->related_id);
        $this->assertSame(WalletLedger::RELATED_CREDIT_NOTE, $entry->related_type);
        $this->assertSame($customer->id, $entry->customer_id);
        $this->assertSame('60.00', $this->balanceOf($workspace, $customer));
        $this->assertTrue(AuditLog::query()->where('action', 'finance.wallet.credited')->exists());
    }

    public function test_a_credit_on_an_unpaid_invoice_leaves_the_wallet_alone(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $this->twoFactor($owner);
        Sanctum::actingAs($owner);

        $this->postJson("/api/workspace/invoices/{$invoice->id}/credit-notes", ['amount' => '100.00', 'reason' => 'Billed in error'])->assertCreated();

        $this->assertSame(0, WalletEntry::query()->count());
    }

    public function test_the_wallet_credit_can_be_paid_back_with_a_voucher(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $this->twoFactor($owner);
        Sanctum::actingAs($owner);
        $this->postJson(self::RECEIPTS, $this->receiptPayload($customer, '100.00', ['allocations' => $this->allocation($invoice, '100.00')]))->assertCreated();
        $this->postJson("/api/workspace/invoices/{$invoice->id}/credit-notes", ['amount' => '25.00', 'reason' => 'Partial refund'])->assertCreated();

        $this->postJson($this->walletUrl($customer, 'returns'), ['amount' => '25.00', 'method' => 'cash', 'reason' => 'Credit note refund'])->assertCreated();
        $this->postJson($this->walletUrl($customer, 'returns'), ['amount' => '0.01', 'method' => 'cash', 'reason' => 'Once more'])->assertUnprocessable();

        $this->assertSame('0.00', $this->balanceOf($workspace, $customer));
    }

    public function test_existing_refund_amounts_become_wallet_credits_once_by_the_migration(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        // A note issued before the wallet existed: refund recorded, no ledger entry.
        $legacy = CreditNote::query()->create([
            'workspace_id' => $workspace->id, 'invoice_id' => $invoice->id, 'number' => 'LEGACY-1',
            'amount' => '40.00', 'refund_amount' => '40.00', 'reason' => 'Old', 'issued_by' => $owner->id, 'issued_at' => now(),
        ]);
        $migration = require database_path('migrations/2026_11_10_110000_credit_wallets_from_existing_credit_notes.php');

        $migration->up();
        $migration->up();

        $this->assertSame(1, WalletEntry::query()->where('related_id', $legacy->id)->count());
        $this->assertSame('40.00', $this->balanceOf($workspace, $customer));
        $this->assertSame(1, DB::table('wallet_entries')->count());
    }
}
