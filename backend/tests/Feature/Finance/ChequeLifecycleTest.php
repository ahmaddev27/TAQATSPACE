<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\InvoiceStatus;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\User;
use App\Models\Workspace;
use Laravel\Sanctum\Sanctum;

class ChequeLifecycleTest extends ReceiptTestCase
{
    /** @return array{0: User, 1: Workspace, 2: User, 3: Invoice, 4: string} */
    private function chequeFor(string $invoiceAmount = '1000.00', string $chequeAmount = '1000.00'): array
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, $invoiceAmount);
        Sanctum::actingAs($owner);

        $id = $this->postJson(self::RECEIPTS, $this->receiptPayload($customer, $chequeAmount, [
            'method' => 'cheque',
            'reference' => 'CHQ-7781',
            'allocations' => $this->allocation($invoice, $chequeAmount),
        ]))->assertCreated()->assertJsonPath('data.cheque_status', 'received')->json('data.id');

        return [$owner, $workspace, $customer, $invoice, $id];
    }

    private function moveTo(string $id, string $status): \Illuminate\Testing\TestResponse
    {
        return $this->putJson(self::RECEIPTS."/{$id}/cheque-status", ['status' => $status]);
    }

    public function test_a_cheque_does_not_count_as_paid_until_it_clears(): void
    {
        [, , , $invoice, $id] = $this->chequeFor();

        $this->assertSame('unpaid', $invoice->fresh()->payment_status->value);
        $this->assertEquals(0, $invoice->fresh()->amount_paid);

        $this->moveTo($id, 'deposited')->assertOk();
        $this->assertSame('unpaid', $invoice->fresh()->payment_status->value);

        $this->moveTo($id, 'cleared')->assertOk()->assertJsonPath('data.is_collected', true);
        $cleared = $invoice->fresh();
        $this->assertSame('paid', $cleared->payment_status->value);
        $this->assertSame(InvoiceStatus::Paid, $cleared->status);
        $this->assertEquals(1000, $cleared->amount_paid);
        $this->assertNotNull(Receipt::query()->find($id)->collected_at);
    }

    public function test_a_bounced_cheque_returns_the_invoice_to_unpaid_or_partial(): void
    {
        [$owner, $workspace, $customer, $invoice, $id] = $this->chequeFor('1000.00', '600.00');
        $this->postJson(self::RECEIPTS, $this->receiptPayload($customer, '100.00', ['allocations' => $this->allocation($invoice, '100.00')]))->assertCreated();
        $this->moveTo($id, 'deposited');
        $this->moveTo($id, 'cleared')->assertOk();

        $this->assertSame('partial', $invoice->fresh()->payment_status->value);
        $this->assertEquals(700, $invoice->fresh()->amount_paid);

        $this->moveTo($id, 'bounced')->assertOk()->assertJsonPath('data.is_collected', false);

        $back = $invoice->fresh();
        $this->assertSame('partial', $back->payment_status->value);
        $this->assertSame(InvoiceStatus::PartiallyPaid, $back->status);
        $this->assertEquals(100, $back->amount_paid);
        $this->assertNull(Receipt::query()->find($id)->collected_at);
    }

    public function test_a_cleared_cheque_that_bounces_leaves_a_fully_paid_invoice_unpaid(): void
    {
        [, , , $invoice, $id] = $this->chequeFor();
        $this->moveTo($id, 'deposited');
        $this->moveTo($id, 'cleared');
        $this->assertSame('paid', $invoice->fresh()->payment_status->value);

        $this->moveTo($id, 'bounced')->assertOk();

        $this->assertSame('unpaid', $invoice->fresh()->payment_status->value);
        $this->assertSame(InvoiceStatus::Pending, $invoice->fresh()->status);
        $this->assertNull($invoice->fresh()->paid_at);
        $this->assertEquals(0, $invoice->fresh()->amount_paid);
        $this->moveTo($id, 'cleared')->assertStatus(409);
    }

    public function test_an_uncleared_cheque_holds_the_balance_until_it_bounces(): void
    {
        [$owner, $workspace, $customer, $invoice, $id] = $this->chequeFor('100.00', '100.00');
        $cash = $this->receiptPayload($customer, '100.00', ['allocations' => $this->allocation($invoice, '100.00')]);

        $this->postJson(self::RECEIPTS, $cash)->assertUnprocessable();

        $this->moveTo($id, 'bounced')->assertOk();
        $this->postJson(self::RECEIPTS, $cash)->assertCreated();
        $this->assertSame('paid', $invoice->fresh()->payment_status->value);
    }

    public function test_only_valid_moves_on_a_cheque_are_allowed(): void
    {
        [, , $customer, , $id] = $this->chequeFor();

        $this->moveTo($id, 'cleared')->assertStatus(409);
        $this->moveTo($id, 'received')->assertStatus(409);
        $this->moveTo($id, 'nonsense')->assertUnprocessable();

        $cash = $this->postJson(self::RECEIPTS, $this->receiptPayload($customer, '10.00'))->json('data.id');
        $this->moveTo($cash, 'deposited')->assertUnprocessable();
    }

    public function test_voiding_a_cleared_cheque_reverses_the_payment_and_changes_are_audited(): void
    {
        [$owner, , , $invoice, $id] = $this->chequeFor();
        $owner->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()])->save();
        app(\App\Services\Auth\TwoFactorService::class)->markVerified($owner);
        $this->moveTo($id, 'deposited');
        $this->moveTo($id, 'cleared');

        $this->postJson(self::RECEIPTS."/{$id}/void", ['reason' => 'Wrong customer'])->assertOk();

        $this->assertSame('unpaid', $invoice->fresh()->payment_status->value);
        $this->assertSame(2, AuditLog::query()->where('action', 'finance.receipt.cheque_changed')->count());
        $this->moveTo($id, 'bounced')->assertStatus(409);
    }
}
