<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\WalletEntryType;
use App\Models\AuditLog;
use App\Models\PaymentVoucher;
use App\Models\WalletEntry;
use Laravel\Sanctum\Sanctum;

class PaymentVoucherTest extends WalletTestCase
{
    public function test_returning_a_deposit_creates_a_numbered_voucher_linked_to_the_entry(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $this->deposit($workspace, $customer, '100.00', $owner);
        Sanctum::actingAs($owner);

        $response = $this->postJson($this->walletUrl($customer, 'returns'), [
            'amount' => '35.00', 'method' => 'transfer', 'reason' => 'Left early', 'reference' => 'TRF-9',
        ])->assertCreated();

        $this->assertStringContainsString('-PAY-', $response->json('data.number'));
        $this->assertStringEndsWith('000001', $response->json('data.number'));
        $response->assertJsonPath('data.amount', '35.00')->assertJsonPath('data.method', 'transfer')->assertJsonPath('data.reference', 'TRF-9');

        $voucher = PaymentVoucher::query()->findOrFail($response->json('data.id'));
        $entry = WalletEntry::query()->findOrFail($voucher->wallet_entry_id);
        $this->assertSame(WalletEntryType::DepositReturn, $entry->type);
        $this->assertEquals(-35, $entry->amount);
        $this->assertSame('65.00', $this->balanceOf($workspace, $customer));
        $this->assertTrue(AuditLog::query()->where('action', 'finance.voucher.created')->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'finance.wallet.deposit_returned')->exists());
    }

    public function test_voucher_numbers_are_gap_free_and_separate_from_other_documents(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $this->invoiceOf($owner, $workspace, $customer, '10.00');
        $this->deposit($workspace, $customer, '100.00', $owner);

        $one = $this->giveBack($workspace, $customer, '10.00', $owner);
        $two = $this->giveBack($workspace, $customer, '10.00', $owner);

        $this->assertStringEndsWith('000001', $one->number);
        $this->assertStringEndsWith('000002', $two->number);
    }

    public function test_a_refused_return_does_not_use_up_a_number(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $this->deposit($workspace, $customer, '10.00', $owner);
        Sanctum::actingAs($owner);

        $this->postJson($this->walletUrl($customer, 'returns'), ['amount' => '11.00', 'method' => 'cash', 'reason' => 'Too much'])->assertUnprocessable();
        $ok = $this->postJson($this->walletUrl($customer, 'returns'), ['amount' => '10.00', 'method' => 'cash', 'reason' => 'All of it'])->assertCreated();

        $this->assertStringEndsWith('000001', $ok->json('data.number'));
    }

    public function test_a_return_needs_a_reason_and_a_manual_method(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $this->deposit($workspace, $customer, '10.00', $owner);
        Sanctum::actingAs($owner);

        $this->postJson($this->walletUrl($customer, 'returns'), ['amount' => '5.00', 'method' => 'cash'])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->postJson($this->walletUrl($customer, 'returns'), ['amount' => '5.00', 'method' => 'wallet', 'reason' => 'Nope'])->assertUnprocessable()->assertJsonValidationErrors('method');
        $this->assertSame(0, PaymentVoucher::query()->count());
    }

    public function test_vouchers_are_listed_for_the_workspace_only_and_filtered(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        [$otherOwner, $otherWorkspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $foreign = $this->subscriber($otherWorkspace);
        $this->deposit($workspace, $customer, '100.00', $owner);
        $this->deposit($otherWorkspace, $foreign, '100.00', $otherOwner);
        $kept = $this->giveBack($workspace, $customer, '10.00', $owner);
        $this->giveBack($otherWorkspace, $foreign, '10.00', $otherOwner);
        Sanctum::actingAs($owner);

        $list = $this->getJson(self::VOUCHERS)->assertOk();
        $this->assertSame(1, $list->json('data.meta.total'));
        $this->assertSame($kept->id, $list->json('data.items.0.id'));
        $this->assertSame($customer->name, $list->json('data.items.0.customer.name'));
        $this->getJson(self::VOUCHERS.'?customer_id='.$foreign->id)->assertOk()->assertJsonPath('data.meta.total', 0);
        $this->getJson(self::VOUCHERS.'?state=voided')->assertOk()->assertJsonPath('data.meta.total', 0);
    }

    public function test_voiding_a_voucher_puts_the_money_back_with_a_reversing_entry_and_deletes_nothing(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $this->deposit($workspace, $customer, '100.00', $owner);
        $voucher = $this->giveBack($workspace, $customer, '40.00', $owner);
        Sanctum::actingAs($owner);
        $this->twoFactor($owner);

        $this->postJson(self::VOUCHERS."/{$voucher->id}/void", ['reason' => 'Paid by mistake'])
            ->assertOk()
            ->assertJsonPath('data.void_reason', 'Paid by mistake');

        $this->assertNotNull($voucher->fresh()->voided_at);
        $this->assertSame('100.00', $this->balanceOf($workspace, $customer));
        $this->assertSame(3, WalletEntry::query()->count());
        $reversal = WalletEntry::query()->where('type', WalletEntryType::Credit)->firstOrFail();
        $this->assertSame($voucher->id, $reversal->related_id);
        $this->assertTrue(AuditLog::query()->where('action', 'finance.voucher.voided')->exists());
    }

    public function test_a_voucher_cannot_be_voided_twice(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $this->deposit($workspace, $customer, '100.00', $owner);
        $voucher = $this->giveBack($workspace, $customer, '40.00', $owner);
        Sanctum::actingAs($owner);
        $this->twoFactor($owner);

        $this->postJson(self::VOUCHERS."/{$voucher->id}/void", ['reason' => 'First'])->assertOk();
        $this->postJson(self::VOUCHERS."/{$voucher->id}/void", ['reason' => 'Second'])->assertStatus(409);

        $this->assertSame('100.00', $this->balanceOf($workspace, $customer));
    }

    public function test_voiding_needs_the_issue_void_grant_a_reason_and_a_recent_two_factor_check(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $this->deposit($workspace, $customer, '100.00', $owner);
        $voucher = $this->giveBack($workspace, $customer, '40.00', $owner);
        $collector = $this->cashierFor($workspace, ['finance_collect']);
        $issuer = $this->cashierFor($workspace, ['finance_issue_void']);

        Sanctum::actingAs($collector);
        $this->postJson(self::VOUCHERS."/{$voucher->id}/void", ['reason' => 'No grant'])->assertForbidden();

        Sanctum::actingAs($issuer);
        $this->postJson(self::VOUCHERS."/{$voucher->id}/void", ['reason' => 'No 2FA'])->assertForbidden();

        Sanctum::actingAs($owner);
        $this->postJson(self::VOUCHERS."/{$voucher->id}/void", ['reason' => 'No 2FA either'])->assertForbidden();
        $this->twoFactor($owner);
        $this->postJson(self::VOUCHERS."/{$voucher->id}/void", [])->assertUnprocessable()->assertJsonValidationErrors('reason');

        $this->assertNull($voucher->fresh()->voided_at);
    }

    public function test_the_pdf_downloads_for_the_workspace_only(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        [$otherOwner] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $this->deposit($workspace, $customer, '100.00', $owner);
        $voucher = $this->giveBack($workspace, $customer, '10.00', $owner);

        Sanctum::actingAs($owner);
        $this->get(self::VOUCHERS."/{$voucher->id}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');

        Sanctum::actingAs($otherOwner);
        $this->get(self::VOUCHERS."/{$voucher->id}/pdf")->assertNotFound();
        $this->postJson(self::VOUCHERS."/{$voucher->id}/void", ['reason' => 'Foreign'])->assertForbidden();
    }
}
