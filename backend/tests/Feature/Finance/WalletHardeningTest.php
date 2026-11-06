<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\PaymentMethod;
use App\Enums\WalletEntryType;
use App\Exceptions\WalletException;
use App\Models\AuditLog;
use App\Models\PaymentVoucher;
use App\Models\Receipt;
use App\Models\WalletEntry;
use App\Models\WorkspaceFeatureFlag;
use App\Notifications\InvoicePaidNotification;
use App\Services\Finance\InvoiceLifecycleService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;

/** Review findings on the wallet endpoints: deposit methods, request keys, isolation and documents. */
class WalletHardeningTest extends WalletTestCase
{
    // ---- M3: only money in hand becomes a deposit ---------------------------

    public function test_a_cheque_deposit_is_refused(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $this->actAsOwner($owner);

        $this->postJson($this->walletUrl($customer, 'deposits'), ['amount' => '100.00', 'method' => 'cheque'])
            ->assertUnprocessable()->assertJsonValidationErrors('method');

        foreach (['cash', 'card', 'transfer'] as $method) {
            $this->postJson($this->walletUrl($customer, 'deposits'), ['amount' => '1.00', 'method' => $method])->assertCreated();
        }
        $this->assertSame('3.00', $this->balanceOf($workspace, $customer));

        $this->expectException(WalletException::class);
        $this->service()->receiveDeposit($workspace, $customer->id, '10.00', PaymentMethod::Cheque, null, null, $owner);
    }

    // ---- M4 / M5: request keys ----------------------------------------------

    public function test_a_deposit_key_that_races_onto_another_customer_answers_409(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $other = $this->subscriber($workspace);
        $this->actAsOwner($owner);
        $this->raceKeyOnto($workspace->id, $other->id, 'form-1');

        $this->postJson($this->walletUrl($customer, 'deposits'), ['amount' => '10.00', 'method' => 'cash', 'request_key' => 'form-1'])
            ->assertStatus(409)->assertJsonPath('code', 'wallet_request_key_reused');

        $this->assertSame('0.00', $this->balanceOf($workspace, $customer));
        $this->assertSame(0, (int) DB::table('customer_wallets')->where('customer_id', $customer->id)->sum('balance'));
    }

    public function test_a_settlement_key_that_races_onto_another_customer_answers_409_and_leaves_no_receipt(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $other = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $this->deposit($workspace, $customer, '100.00', $owner);
        $this->actAsOwner($owner);
        $this->raceKeyOnto($workspace->id, $other->id, 'pay-1');

        $this->postJson($this->walletUrl($customer, 'settlements'), ['invoice_id' => $invoice->id, 'amount' => '40.00', 'request_key' => 'pay-1'])
            ->assertStatus(409)->assertJsonPath('code', 'wallet_request_key_reused');

        $this->assertSame(0, Receipt::query()->count());
        $this->assertEquals(0, $invoice->fresh()->amount_paid);
        $this->assertSame('100.00', $this->balanceOf($workspace, $customer));
    }

    public function test_a_key_reused_for_another_customer_answers_409(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $other = $this->subscriber($workspace);
        $this->actAsOwner($owner);
        $payload = ['amount' => '10.00', 'method' => 'cash', 'request_key' => 'form-2'];

        $this->postJson($this->walletUrl($customer, 'deposits'), $payload)->assertCreated();
        $this->postJson($this->walletUrl($customer, 'deposits'), $payload)->assertCreated();
        $this->postJson($this->walletUrl($other, 'deposits'), $payload)->assertStatus(409)->assertJsonPath('code', 'wallet_request_key_reused');

        $this->assertSame('10.00', $this->balanceOf($workspace, $customer));
        $this->assertSame('0.00', $this->balanceOf($workspace, $other));
    }

    // ---- M5: the invoice-paid message goes out only after commit ---------------

    public function test_settling_sends_the_paid_message_once_it_has_committed(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $this->deposit($workspace, $customer, '100.00', $owner);

        $this->service()->settleAgainstInvoice($workspace, $customer->id, $invoice->id, '100.00', null, $owner);

        Notification::assertSentToTimes($customer, InvoicePaidNotification::class, 1);
    }

    public function test_a_settlement_that_rolls_back_sends_no_paid_message(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $this->deposit($workspace, $customer, '100.00', $owner);
        WalletEntry::creating(static function (WalletEntry $entry): void {
            if ($entry->type === WalletEntryType::Settlement) {
                throw new RuntimeException('The ledger write failed after the receipt.');
            }
        });

        try {
            $this->service()->settleAgainstInvoice($workspace, $customer->id, $invoice->id, '100.00', null, $owner);
            $this->fail('The settlement must fail.');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(0, Receipt::query()->count());
        Notification::assertNotSentTo($customer, InvoicePaidNotification::class);
    }

    // ---- K3: the wallet customer rule ---------------------------------------

    public function test_return_forfeit_and_settle_refuse_someone_who_is_not_a_customer_here(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        [$otherOwner, $other] = $this->owningWorkspace();
        $stranger = $this->subscriber($other);
        $this->deposit($other, $stranger, '100.00', $otherOwner);
        $invoice = $this->invoiceOf($otherOwner, $other, $stranger, '50.00');
        $this->actAsOwner($owner);
        $unknown = __('messages.wallet_unknown_customer', [], 'en');

        $this->postJson($this->walletUrl($stranger, 'returns'), ['amount' => '10.00', 'method' => 'cash', 'reason' => 'Foreign'])
            ->assertUnprocessable()->assertJsonPath('message', $unknown);
        $this->postJson($this->walletUrl($stranger, 'forfeits'), ['amount' => '10.00', 'reason' => 'Foreign'])
            ->assertUnprocessable()->assertJsonPath('message', $unknown);
        $this->postJson($this->walletUrl($stranger, 'settlements'), ['invoice_id' => $invoice->id, 'amount' => '10.00'])
            ->assertUnprocessable()->assertJsonPath('message', $unknown);

        $this->assertSame('100.00', $this->balanceOf($other, $stranger));
        $this->assertSame(0, WalletEntry::query()->where('workspace_id', $workspace->id)->count());
        $this->assertSame(0, DB::table('customer_wallets')->where('workspace_id', $workspace->id)->count());
    }

    public function test_a_former_customer_with_wallet_history_can_still_be_paid_back(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $this->deposit($workspace, $customer, '40.00', $owner);
        DB::table('subscriptions')->where('member_id', $customer->id)->delete();
        $this->actAsOwner($owner);

        $this->postJson($this->walletUrl($customer, 'returns'), ['amount' => '40.00', 'method' => 'cash', 'reason' => 'Leaving'])->assertCreated();

        $this->assertSame('0.00', $this->balanceOf($workspace, $customer));
    }

    public function test_a_voucher_of_another_workspace_cannot_be_voided_or_downloaded(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        [$otherOwner] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $this->deposit($workspace, $customer, '100.00', $owner);
        $voucher = $this->giveBack($workspace, $customer, '10.00', $owner);
        $this->actAsOwner($otherOwner);

        $this->postJson(self::VOUCHERS."/{$voucher->id}/void", ['reason' => 'Foreign'])->assertNotFound();
        $this->get(self::VOUCHERS."/{$voucher->id}/pdf")->assertNotFound();

        $this->assertNull($voucher->fresh()->voided_at);
        $this->assertSame('90.00', $this->balanceOf($workspace, $customer));
    }

    public function test_the_voucher_pdf_and_void_do_not_exist_while_finance_v2_is_off(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $this->deposit($workspace, $customer, '100.00', $owner);
        $voucher = $this->giveBack($workspace, $customer, '10.00', $owner);
        // The workspace turns finance v2 off after using the wallet.
        WorkspaceFeatureFlag::query()->where('workspace_id', $workspace->id)->update(['enabled' => false]);
        Cache::flush();
        $this->actAsOwner($owner);

        $this->get(self::VOUCHERS."/{$voucher->id}/pdf")->assertNotFound();
        $this->postJson(self::VOUCHERS."/{$voucher->id}/void", ['reason' => 'Flag is off'])->assertNotFound();
        $this->postJson($this->walletUrl($customer, 'entries/'.$voucher->wallet_entry_id.'/reverse'), ['reason' => 'Flag is off'])->assertNotFound();

        $this->assertNull($voucher->fresh()->voided_at);
    }

    // ---- Documents and inputs --------------------------------------------------

    public function test_the_wallet_list_sends_the_last_entry_time_as_iso_8601_utc_and_exact_cents(): void
    {
        Carbon::setTestNow('2026-02-01 23:30:00');
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $this->deposit($workspace, $customer, '0.70', $owner);
        $this->deposit($workspace, $customer, '0.10', $owner);
        $this->actAsOwner($owner);

        $row = $this->getJson(self::WALLET)->assertOk()->json('data.items.0');

        $this->assertSame('2026-02-01T23:30:00+00:00', $row['last_entry_at']);
        $this->assertSame('0.80', $row['balance']);
        Carbon::setTestNow();
    }

    public function test_a_voucher_cannot_be_dated_in_the_future(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $this->deposit($workspace, $customer, '50.00', $owner);
        $this->actAsOwner($owner);
        $payload = ['amount' => '10.00', 'method' => 'cash', 'reason' => 'Leaving'];

        $this->postJson($this->walletUrl($customer, 'returns'), $payload + ['paid_at' => now()->addDay()->toDateString()])
            ->assertUnprocessable()->assertJsonValidationErrors('paid_at');
        $this->postJson($this->walletUrl($customer, 'returns'), $payload + ['paid_at' => now()->subDay()->toDateString()])->assertCreated();
    }

    public function test_the_wallet_page_size_and_the_void_reason_are_validated(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $this->deposit($workspace, $customer, '50.00', $owner);
        $voucher = $this->giveBack($workspace, $customer, '10.00', $owner);
        $this->actAsOwner($owner);

        $this->getJson($this->walletUrl($customer).'?per_page=0')->assertUnprocessable()->assertJsonValidationErrors('per_page');
        $this->getJson($this->walletUrl($customer).'?per_page=500')->assertUnprocessable()->assertJsonValidationErrors('per_page');
        $this->postJson(self::VOUCHERS."/{$voucher->id}/void", ['reason' => 'no'])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->postJson(self::VOUCHERS."/{$voucher->id}/void", ['reason' => str_repeat('x', 501)])->assertUnprocessable()->assertJsonValidationErrors('reason');
    }

    public function test_a_voucher_keeps_its_amount_number_customer_and_method(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $this->deposit($workspace, $customer, '50.00', $owner);
        $voucher = $this->giveBack($workspace, $customer, '10.00', $owner);

        foreach (['amount' => '99.00', 'number' => 'X-1', 'customer_id' => $owner->id, 'method' => PaymentMethod::Card] as $field => $value) {
            try {
                $voucher->fresh()->forceFill([$field => $value])->save();
                $this->fail("{$field} must not change.");
            } catch (LogicException) {
                // expected
            }
        }

        $voucher->fresh()->forceFill(['reference' => 'TRF-9'])->save();
        $this->assertEquals(10, $voucher->fresh()->amount);
    }

    public function test_the_voucher_pdf_prints_the_method_name_not_its_code(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $this->deposit($workspace, $customer, '50.00', $owner);
        $voucher = $this->service()->returnDeposit($workspace, $customer->id, '10.00', PaymentMethod::Transfer, 'Leaving', null, null, null, $owner);

        $html = view('pdf.payment-voucher', ['voucher' => $voucher->fresh()->load('customer', 'workspace')])->render();

        $this->assertStringContainsString('Bank transfer', $html);
        $this->assertStringContainsString('تحويل بنكي', $html);
        $this->assertStringNotContainsString('<td>transfer</td>', $html);
    }

    public function test_an_invoice_paid_from_the_wallet_cannot_be_voided_until_the_wallet_receipt_is(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $this->deposit($workspace, $customer, '100.00', $owner);
        $receipt = $this->service()->settleAgainstInvoice($workspace, $customer->id, $invoice->id, '60.00', null, $owner);

        try {
            app(InvoiceLifecycleService::class)->void($invoice->fresh(), 'Billed twice', $owner);
            $this->fail('The wallet money would be stranded on a void invoice.');
        } catch (\App\Exceptions\InvoiceLifecycleException $e) {
            $this->assertSame(__('messages.invoice_void_has_wallet_payment'), $e->getMessage());
        }

        app(\App\Services\Finance\ReceiptService::class)->void($receipt->fresh(), 'Wrong invoice', $owner);
        app(InvoiceLifecycleService::class)->void($invoice->fresh(), 'Billed twice', $owner);
        $this->assertSame('100.00', $this->balanceOf($workspace, $customer));
    }

    // ---- Audit trail ----------------------------------------------------------

    public function test_deposit_return_settle_and_reversal_each_leave_an_audit_entry_with_the_amount(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        $deposit = $this->deposit($workspace, $customer, '100.00', $owner);
        $this->deposit($workspace, $customer, '5.00', $owner);
        $voucher = $this->giveBack($workspace, $customer, '10.00', $owner);
        $this->service()->settleAgainstInvoice($workspace, $customer->id, $invoice->id, '20.00', null, $owner);
        $wrong = $this->deposit($workspace, $customer, '7.00', $owner);
        $this->service()->reverseEntry($workspace, $customer->id, $wrong->id, 'Typed twice', null, $owner);

        $amounts = static fn (string $action): array => AuditLog::query()->where('action', $action)->where('workspace_id', $workspace->id)
            ->get()->map(fn (AuditLog $log): string => (string) $log->changes['amount'])->sort()->values()->all();

        $this->assertSame(['5.00', '7.00', '100.00'], $amounts('finance.wallet.deposit_received'));
        $this->assertSame(['-10.00'], $amounts('finance.wallet.deposit_returned'));
        $this->assertSame(['10.00'], $amounts('finance.voucher.created'));
        $this->assertSame(['-20.00'], $amounts('finance.wallet.settled'));
        $this->assertSame(['-7.00'], $amounts('finance.wallet.entry_reversed'));
        $this->assertSame($voucher->number, AuditLog::query()->where('action', 'finance.wallet.deposit_returned')->firstOrFail()->changes['voucher']);
        $this->assertNotNull($deposit->fresh());
    }

    /** An entry with `$key` for another customer appears just before this request writes its own. */
    private function raceKeyOnto(string $workspaceId, string $customerId, string $key): void
    {
        $raced = false;
        // The two wallets lock apart, so a request on the other one can commit between this
        // request's key lookup and its insert; only the unique index sees that.
        WalletEntry::creating(static function (WalletEntry $entry) use (&$raced, $workspaceId, $customerId, $key): void {
            if ($raced || $entry->request_key !== $key) {
                return;
            }

            $raced = true;
            DB::table('wallet_entries')->insert([
                'id' => (string) Str::uuid(),
                'workspace_id' => $workspaceId,
                'customer_id' => $customerId,
                'type' => WalletEntryType::DepositIn->value,
                'amount' => '10.00',
                'request_key' => $key,
                'created_at' => now(),
            ]);
        });
    }
}
