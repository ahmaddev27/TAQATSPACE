<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\AuditLog;
use App\Models\WalletEntry;
use Laravel\Sanctum\Sanctum;

class WalletApiTest extends WalletTestCase
{
    public function test_a_deposit_is_recorded_and_raises_the_balance(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        Sanctum::actingAs($owner);

        $this->postJson($this->walletUrl($customer, 'deposits'), ['amount' => '150.50', 'method' => 'cash', 'reason' => 'Key deposit'])
            ->assertCreated()
            ->assertJsonPath('data.type', 'deposit_in')
            ->assertJsonPath('data.amount', '150.50');

        $show = $this->getJson($this->walletUrl($customer))->assertOk();
        $this->assertSame('150.50', $show->json('data.balance'));
        $this->assertSame($workspace->fresh()->currency, $show->json('data.currency'));
        $this->assertCount(1, $show->json('data.items'));
        $this->assertTrue(AuditLog::query()->where('action', 'finance.wallet.deposit_received')->where('workspace_id', $workspace->id)->exists());
    }

    public function test_a_deposit_can_not_be_recorded_as_a_wallet_payment_or_with_a_bad_amount(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        Sanctum::actingAs($owner);

        $this->postJson($this->walletUrl($customer, 'deposits'), ['amount' => '10.00', 'method' => 'wallet'])->assertUnprocessable()->assertJsonValidationErrors('method');
        $this->postJson($this->walletUrl($customer, 'deposits'), ['amount' => '0', 'method' => 'cash'])->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->postJson($this->walletUrl($customer, 'deposits'), ['amount' => '-5', 'method' => 'cash'])->assertUnprocessable();
        $this->postJson($this->walletUrl($customer, 'deposits'), ['amount' => '1.999', 'method' => 'cash'])->assertUnprocessable();
        $this->assertSame(0, WalletEntry::query()->count());
    }

    public function test_the_wallet_is_not_a_way_to_record_a_manual_receipt(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        Sanctum::actingAs($owner);

        $this->postJson(self::RECEIPTS, $this->receiptPayload($customer, '10.00', ['method' => 'wallet']))
            ->assertUnprocessable()->assertJsonValidationErrors('method');
    }

    public function test_returning_a_deposit_larger_than_the_balance_is_a_422(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $this->deposit($workspace, $customer, '80.00', $owner);
        Sanctum::actingAs($owner);

        $this->postJson($this->walletUrl($customer, 'returns'), ['amount' => '80.01', 'method' => 'cash', 'reason' => 'Leaving'])->assertUnprocessable();

        $this->assertSame('80.00', $this->balanceOf($workspace, $customer));
    }

    public function test_the_same_return_sent_twice_pays_out_once(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $this->deposit($workspace, $customer, '80.00', $owner);
        Sanctum::actingAs($owner);
        $payload = ['amount' => '80.00', 'method' => 'cash', 'reason' => 'Leaving'];

        $this->postJson($this->walletUrl($customer, 'returns'), $payload)->assertCreated();
        $this->postJson($this->walletUrl($customer, 'returns'), $payload)->assertUnprocessable();

        $this->assertSame('0.00', $this->balanceOf($workspace, $customer));
    }

    public function test_the_same_return_with_a_request_key_answers_with_the_first_voucher(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $this->deposit($workspace, $customer, '80.00', $owner);
        Sanctum::actingAs($owner);
        $payload = ['amount' => '30.00', 'method' => 'cash', 'reason' => 'Leaving', 'request_key' => 'abc-123'];

        $first = $this->postJson($this->walletUrl($customer, 'returns'), $payload)->assertCreated();
        $second = $this->postJson($this->walletUrl($customer, 'returns'), $payload)->assertCreated();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame('50.00', $this->balanceOf($workspace, $customer));
    }

    public function test_settling_pays_an_invoice_from_the_wallet_through_a_receipt(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $this->deposit($workspace, $customer, '100.00', $owner);
        Sanctum::actingAs($owner);

        $response = $this->postJson($this->walletUrl($customer, 'settlements'), ['invoice_id' => $invoice->id, 'amount' => '100.00'])->assertCreated();

        $response->assertJsonPath('data.method', 'wallet');
        $this->assertStringContainsString('-REC-', $response->json('data.number'));
        $this->assertSame('paid', $invoice->fresh()->payment_status->value);
        $this->assertSame('0.00', $this->balanceOf($workspace, $customer));
        $this->assertTrue(AuditLog::query()->where('action', 'finance.wallet.settled')->exists());
    }

    public function test_settling_more_than_the_balance_or_the_invoice_is_refused(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $this->deposit($workspace, $customer, '50.00', $owner);
        Sanctum::actingAs($owner);

        $this->postJson($this->walletUrl($customer, 'settlements'), ['invoice_id' => $invoice->id, 'amount' => '50.01'])->assertUnprocessable();
        $this->assertSame('unpaid', $invoice->fresh()->payment_status->value);

        $cheap = $this->invoiceOf($owner, $workspace, $customer, '10.00');
        $this->postJson($this->walletUrl($customer, 'settlements'), ['invoice_id' => $cheap->id, 'amount' => '10.01'])->assertUnprocessable();
        $this->assertSame('50.00', $this->balanceOf($workspace, $customer));
    }

    public function test_voiding_a_wallet_receipt_puts_the_money_back_on_the_wallet(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $this->deposit($workspace, $customer, '100.00', $owner);
        Sanctum::actingAs($owner);
        $this->twoFactor($owner);
        $receiptId = $this->postJson($this->walletUrl($customer, 'settlements'), ['invoice_id' => $invoice->id, 'amount' => '60.00'])->json('data.id');

        $this->postJson(self::RECEIPTS."/{$receiptId}/void", ['reason' => 'Wrong invoice'])->assertOk();

        $this->assertSame('100.00', $this->balanceOf($workspace, $customer));
        $this->assertSame('unpaid', $invoice->fresh()->payment_status->value);
        $this->assertTrue(AuditLog::query()->where('action', 'finance.wallet.settlement_reversed')->exists());
        $this->postJson(self::RECEIPTS."/{$receiptId}/void", ['reason' => 'Again'])->assertStatus(409);
        $this->assertSame('100.00', $this->balanceOf($workspace, $customer));
    }

    public function test_balances_are_listed_for_the_workspace_only(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        [$otherOwner, $otherWorkspace] = $this->owningWorkspace();
        $mine = $this->subscriber($workspace);
        $theirs = $this->subscriber($otherWorkspace);
        $this->deposit($workspace, $mine, '30.00', $owner);
        $this->deposit($workspace, $mine, '20.00', $owner);
        $this->deposit($otherWorkspace, $theirs, '99.00', $otherOwner);
        Sanctum::actingAs($owner);

        $list = $this->getJson(self::WALLET)->assertOk();

        $this->assertSame(1, $list->json('data.meta.total'));
        $this->assertSame($mine->id, $list->json('data.items.0.customer.id'));
        $this->assertSame('50.00', $list->json('data.items.0.balance'));
        $this->assertSame($mine->name, $list->json('data.items.0.customer.name'));
        $this->getJson($this->walletUrl($theirs))->assertNotFound();
        $this->postJson($this->walletUrl($theirs, 'deposits'), ['amount' => '5.00', 'method' => 'cash'])->assertUnprocessable();
    }

    public function test_permissions_split_collecting_from_paying_money_back(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $this->deposit($workspace, $customer, '100.00', $owner);
        $collector = $this->cashierFor($workspace, ['finance_collect']);
        $reporter = $this->cashierFor($workspace, ['finance_reports']);
        $nobody = $this->cashierFor($workspace, ['finance_view_pos']);
        $deposit = ['amount' => '5.00', 'method' => 'cash'];
        $back = ['amount' => '5.00', 'method' => 'cash', 'reason' => 'Because'];

        Sanctum::actingAs($collector);
        $this->getJson(self::WALLET)->assertOk();
        $this->postJson($this->walletUrl($customer, 'deposits'), $deposit)->assertCreated();
        $this->postJson($this->walletUrl($customer, 'returns'), $back)->assertForbidden();
        $this->postJson($this->walletUrl($customer, 'forfeits'), $back)->assertForbidden();

        Sanctum::actingAs($reporter);
        $this->getJson($this->walletUrl($customer))->assertOk();
        $this->postJson($this->walletUrl($customer, 'deposits'), $deposit)->assertForbidden();
        $this->postJson($this->walletUrl($customer, 'returns'), $back)->assertForbidden();

        Sanctum::actingAs($nobody);
        $this->getJson(self::WALLET)->assertForbidden();
    }

    public function test_the_endpoints_do_not_exist_while_finance_v2_is_off(): void
    {
        [$owner, $workspace] = $this->owningWorkspace(financeV2: false);
        $customer = $this->subscriber($workspace);
        Sanctum::actingAs($owner);
        $this->twoFactor($owner);

        $this->getJson(self::WALLET)->assertNotFound();
        $this->getJson($this->walletUrl($customer))->assertNotFound();
        $this->postJson($this->walletUrl($customer, 'deposits'), ['amount' => '5.00', 'method' => 'cash'])->assertNotFound();
        $this->postJson($this->walletUrl($customer, 'returns'), ['amount' => '5.00', 'method' => 'cash', 'reason' => 'Off'])->assertNotFound();
        $this->postJson($this->walletUrl($customer, 'settlements'), ['amount' => '5.00', 'invoice_id' => $customer->id])->assertNotFound();
        $this->postJson($this->walletUrl($customer, 'forfeits'), ['amount' => '5.00', 'reason' => 'Off'])->assertNotFound();
        $this->getJson(self::VOUCHERS)->assertNotFound();
        $this->assertSame(0, WalletEntry::query()->count());
    }
}
