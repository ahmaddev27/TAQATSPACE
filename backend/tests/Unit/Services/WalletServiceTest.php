<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\PaymentMethod;
use App\Enums\WalletEntryType;
use App\Exceptions\WalletException;
use App\Models\PaymentVoucher;
use App\Models\WalletEntry;
use App\Services\Finance\WalletLedger;
use LogicException;
use Tests\Feature\Finance\WalletTestCase;

class WalletServiceTest extends WalletTestCase
{
    public function test_the_balance_is_the_sum_of_the_signed_entries(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);

        $this->deposit($workspace, $customer, '100.00', $owner);
        $this->giveBack($workspace, $customer, '30.00', $owner);
        $this->service()->forfeit($workspace, $customer->id, '20.00', 'Damage', null, $owner);

        $this->assertSame('50.00', $this->balanceOf($workspace, $customer));
        $this->assertEquals(50, WalletEntry::query()->sum('amount'));
        $this->assertSame(['-30.00', '-20.00', '100.00'], WalletEntry::query()->orderBy('amount')->get()->map(fn (WalletEntry $e): string => (string) $e->amount)->all());
    }

    public function test_cents_add_up_exactly(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);

        $this->deposit($workspace, $customer, '0.10', $owner);
        $this->deposit($workspace, $customer, '0.20', $owner);

        $this->assertSame('0.30', $this->balanceOf($workspace, $customer));
        $this->giveBack($workspace, $customer, '0.30', $owner);
        $this->assertSame('0.00', $this->balanceOf($workspace, $customer));
    }

    public function test_a_wallet_with_no_entries_has_a_zero_balance(): void
    {
        [, $workspace] = $this->owningWorkspace();

        $this->assertSame('0.00', $this->balanceOf($workspace, $this->subscriber($workspace)));
    }

    public function test_balances_are_per_workspace(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        [, $other] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $this->deposit($workspace, $customer, '40.00', $owner);

        $this->assertSame('0.00', $this->balanceOf($other, $customer));
    }

    public function test_returning_more_than_the_balance_is_refused_and_writes_nothing(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $this->deposit($workspace, $customer, '50.00', $owner);

        try {
            $this->giveBack($workspace, $customer, '50.01', $owner);
            $this->fail('An over-withdrawal must be refused.');
        } catch (WalletException) {
            // expected
        }

        $this->assertSame('50.00', $this->balanceOf($workspace, $customer));
        $this->assertSame(1, WalletEntry::query()->count());
        $this->assertSame(0, PaymentVoucher::query()->count());
    }

    public function test_the_balance_can_be_withdrawn_exactly_but_never_twice(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $this->deposit($workspace, $customer, '50.00', $owner);

        $this->giveBack($workspace, $customer, '50.00', $owner);
        $this->assertSame('0.00', $this->balanceOf($workspace, $customer));

        $this->expectException(WalletException::class);
        $this->giveBack($workspace, $customer, '0.01', $owner);
    }

    public function test_forfeiting_more_than_the_balance_is_refused(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $this->deposit($workspace, $customer, '10.00', $owner);

        $this->expectException(WalletException::class);
        $this->service()->forfeit($workspace, $customer->id, '10.01', 'Too much', null, $owner);
    }

    public function test_settling_more_than_the_balance_is_refused_and_leaves_the_invoice_alone(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $this->deposit($workspace, $customer, '30.00', $owner);

        try {
            $this->service()->settleAgainstInvoice($workspace, $customer->id, $invoice->id, '30.01', null, $owner);
            $this->fail('An over-withdrawal must be refused.');
        } catch (WalletException) {
            // expected
        }

        $this->assertSame('unpaid', $invoice->fresh()->payment_status->value);
        $this->assertSame('30.00', $this->balanceOf($workspace, $customer));
    }

    public function test_settling_pays_the_invoice_and_takes_the_money_from_the_wallet(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $this->deposit($workspace, $customer, '70.00', $owner);

        $receipt = $this->service()->settleAgainstInvoice($workspace, $customer->id, $invoice->id, '60.00', null, $owner);

        $this->assertSame(PaymentMethod::Wallet, $receipt->method);
        $this->assertEquals(60, $invoice->fresh()->amount_paid);
        $this->assertSame('partial', $invoice->fresh()->payment_status->value);
        $this->assertSame('10.00', $this->balanceOf($workspace, $customer));
        $entry = WalletEntry::query()->where('type', WalletEntryType::Settlement)->firstOrFail();
        $this->assertSame($receipt->id, $entry->related_id);
        $this->assertSame(WalletLedger::RELATED_RECEIPT, $entry->related_type);
    }

    public function test_settling_more_than_the_invoice_owes_is_refused_and_the_wallet_is_untouched(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '20.00');
        $this->deposit($workspace, $customer, '100.00', $owner);

        try {
            $this->service()->settleAgainstInvoice($workspace, $customer->id, $invoice->id, '20.01', null, $owner);
            $this->fail('The invoice owes only 20.00.');
        } catch (\Throwable) {
            // expected
        }

        $this->assertSame('100.00', $this->balanceOf($workspace, $customer));
        $this->assertSame(0, \App\Models\Receipt::query()->count());
    }

    public function test_a_repeated_request_key_writes_one_entry_and_one_voucher(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $this->deposit($workspace, $customer, '100.00', $owner);

        $first = $this->giveBack($workspace, $customer, '40.00', $owner, 'same-click');
        $second = $this->giveBack($workspace, $customer, '40.00', $owner, 'same-click');

        $this->assertSame($first->id, $second->id);
        $this->assertSame('60.00', $this->balanceOf($workspace, $customer));
        $this->assertSame(1, PaymentVoucher::query()->count());
    }

    public function test_a_repeated_deposit_key_does_not_double_the_deposit(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);

        $service = $this->service();
        $service->receiveDeposit($workspace, $customer->id, '25.00', PaymentMethod::Cash, null, 'dep-1', $owner);
        $service->receiveDeposit($workspace, $customer->id, '25.00', PaymentMethod::Cash, null, 'dep-1', $owner);

        $this->assertSame('25.00', $this->balanceOf($workspace, $customer));
    }

    public function test_a_repeated_settlement_key_pays_the_invoice_once(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $this->deposit($workspace, $customer, '100.00', $owner);

        $first = $this->service()->settleAgainstInvoice($workspace, $customer->id, $invoice->id, '40.00', 'pay-1', $owner);
        $second = $this->service()->settleAgainstInvoice($workspace, $customer->id, $invoice->id, '40.00', 'pay-1', $owner);

        $this->assertSame($first->id, $second->id);
        $this->assertEquals(40, $invoice->fresh()->amount_paid);
        $this->assertSame('60.00', $this->balanceOf($workspace, $customer));
    }

    public function test_a_key_cannot_be_reused_for_a_different_operation(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $this->deposit($workspace, $customer, '100.00', $owner);
        $this->giveBack($workspace, $customer, '10.00', $owner, 'key-1');

        $this->expectException(WalletException::class);
        $this->giveBack($workspace, $customer, '20.00', $owner, 'key-1');
    }

    public function test_an_amount_must_be_positive(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);

        $this->expectException(WalletException::class);
        $this->deposit($workspace, $customer, '0.00', $owner);
    }

    public function test_only_a_customer_of_the_workspace_can_leave_a_deposit(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        [, $other] = $this->owningWorkspace();
        $stranger = $this->subscriber($other);

        $this->expectException(WalletException::class);
        $this->deposit($workspace, $stranger, '10.00', $owner);
    }

    public function test_ledger_entries_cannot_be_changed_or_deleted(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $entry = $this->deposit($workspace, $this->subscriber($workspace), '10.00', $owner);

        try {
            $entry->delete();
            $this->fail('A wallet entry must not be deletable.');
        } catch (LogicException) {
            // expected
        }

        try {
            $entry->forceFill(['amount' => '999.00'])->save();
            $this->fail('A wallet entry must not be editable.');
        } catch (LogicException) {
            // expected
        }

        $this->assertEquals(10, $entry->fresh()->amount);
    }

    public function test_a_voucher_cannot_be_deleted(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $this->deposit($workspace, $customer, '10.00', $owner);
        $voucher = $this->giveBack($workspace, $customer, '5.00', $owner);

        $this->expectException(LogicException::class);
        $voucher->delete();
    }
}
