<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\AuditLog;
use App\Models\CreditNote;
use App\Services\Auth\TwoFactorService;
use App\Services\Finance\InvoiceLifecycleService;
use Laravel\Sanctum\Sanctum;

class CreditNoteTest extends ReceiptTestCase
{
    private const LIST = '/api/workspace/finance/credit-notes';

    public function test_a_full_credit_note_on_an_unpaid_invoice_clears_the_receivable_and_leaves_the_invoice_untouched(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $invoice = $this->invoiceOf($owner, $workspace, $this->subscriber($workspace), '100.00');
        $before = $invoice->fresh();
        Sanctum::actingAs($owner);
        $this->twoFactor($owner);

        $response = $this->postJson($this->url($invoice), ['amount' => '100.00', 'reason' => 'Billed in error'])->assertCreated();

        $response->assertJsonPath('data.reason', 'Billed in error');
        $response->assertJsonMissingPath('data.refund_amount');
        $this->getJson("/api/workspace/invoices/{$invoice->id}")->assertJsonPath('data.refund_due', '0.00');
        $this->assertStringContainsString('-CRE-', $response->json('data.number'));

        $after = $invoice->fresh();
        $this->assertEquals(0, app(\App\Services\Finance\InvoiceBalanceService::class)->unpaid($after));
        $this->assertSame('paid', $after->payment_status->value);
        $this->assertEquals(100, $after->credited_amount);
        $this->assertEquals($before->amount, $after->amount);
        $this->assertEquals($before->total, $after->total);
        $this->assertSame($before->invoice_number, $after->invoice_number);
        $this->assertSame('issued', $after->doc_status->value);
        $this->assertSame($before->lines()->count(), $after->lines()->count());
    }

    public function test_a_partial_credit_reduces_the_outstanding_balance_and_settles_with_the_remaining_payment(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        Sanctum::actingAs($owner);
        $this->twoFactor($owner);

        $this->postJson($this->url($invoice), ['amount' => '30.00', 'reason' => 'Discount agreed'])->assertCreated();
        $this->assertSame('unpaid', $invoice->fresh()->payment_status->value);

        $this->postJson(self::RECEIPTS, $this->receiptPayload($customer, '80.00', [
            'allocations' => $this->allocation($invoice, '71.00'),
        ]))->assertUnprocessable();

        $this->postJson(self::RECEIPTS, $this->receiptPayload($customer, '70.00', [
            'allocations' => $this->allocation($invoice, '70.00'),
        ]))->assertCreated();

        $this->assertSame('paid', $invoice->fresh()->payment_status->value);
    }

    public function test_crediting_a_paid_amount_records_the_refund_owed_back(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        Sanctum::actingAs($owner);
        $this->twoFactor($owner);
        $this->postJson(self::RECEIPTS, $this->receiptPayload($customer, '60.00', [
            'allocations' => $this->allocation($invoice, '60.00'),
        ]))->assertCreated();

        $this->postJson($this->url($invoice), ['amount' => '100.00', 'reason' => 'Service cancelled'])->assertCreated();

        // Owed back is the invoice's figure, current on every read.
        $this->getJson("/api/workspace/invoices/{$invoice->id}")->assertJsonPath('data.refund_due', '60.00');
        $this->assertEquals(0, app(\App\Services\Finance\InvoiceBalanceService::class)->unpaid($invoice->fresh()));
    }

    public function test_a_draft_invoice_cannot_be_credited(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $draft = $this->draftFor($owner, $workspace);
        Sanctum::actingAs($owner);
        $this->twoFactor($owner);

        $this->postJson($this->url($draft), ['amount' => '1.00', 'reason' => 'Nope'])->assertUnprocessable();
        $this->assertSame(0, CreditNote::query()->count());
    }

    public function test_a_void_invoice_cannot_be_credited(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $invoice = $this->invoiceOf($owner, $workspace, $this->subscriber($workspace), '100.00');
        app(InvoiceLifecycleService::class)->void($invoice, 'Mistake', $owner);
        Sanctum::actingAs($owner);
        $this->twoFactor($owner);

        $this->postJson($this->url($invoice), ['amount' => '10.00', 'reason' => 'Nope'])->assertUnprocessable();
    }

    public function test_the_credit_cannot_exceed_the_total_less_earlier_credit_notes(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $invoice = $this->invoiceOf($owner, $workspace, $this->subscriber($workspace), '100.00');
        Sanctum::actingAs($owner);
        $this->twoFactor($owner);

        $this->postJson($this->url($invoice), ['amount' => '100.01', 'reason' => 'Too much'])->assertUnprocessable();
        $this->postJson($this->url($invoice), ['amount' => '70.00', 'reason' => 'First'])->assertCreated();
        $this->postJson($this->url($invoice), ['amount' => '30.01', 'reason' => 'Second'])->assertUnprocessable();
        $this->postJson($this->url($invoice), ['amount' => '30.00', 'reason' => 'Second'])->assertCreated();

        $this->assertSame(2, CreditNote::query()->count());
        $this->assertEquals(100, $invoice->fresh()->credited_amount);
    }

    public function test_a_pending_cheque_limits_the_credit_to_the_unpromised_part(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $customer = $this->subscriber($workspace);
        $invoice = $this->invoiceOf($owner, $workspace, $customer, '100.00');
        Sanctum::actingAs($owner);
        $this->twoFactor($owner);
        $this->postJson(self::RECEIPTS, $this->receiptPayload($customer, '40.00', [
            'method' => 'cheque',
            'allocations' => $this->allocation($invoice, '40.00'),
        ]))->assertCreated();

        $this->postJson($this->url($invoice), ['amount' => '60.01', 'reason' => 'Too much'])->assertUnprocessable();
        $this->postJson($this->url($invoice), ['amount' => '60.00', 'reason' => 'Fine'])->assertCreated();
    }

    public function test_a_reason_and_a_positive_amount_are_required(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $invoice = $this->invoiceOf($owner, $workspace, $this->subscriber($workspace), '100.00');
        Sanctum::actingAs($owner);
        $this->twoFactor($owner);

        $this->postJson($this->url($invoice), ['amount' => '10.00'])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->postJson($this->url($invoice), ['amount' => '10.00', 'reason' => ''])->assertUnprocessable();
        $this->postJson($this->url($invoice), ['amount' => '0', 'reason' => 'Zero'])->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->postJson($this->url($invoice), ['amount' => '-5', 'reason' => 'Negative'])->assertUnprocessable();
    }

    public function test_every_credit_note_is_in_the_activity_log(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $invoice = $this->invoiceOf($owner, $workspace, $this->subscriber($workspace), '100.00');
        Sanctum::actingAs($owner);
        $this->twoFactor($owner);

        $this->postJson($this->url($invoice), ['amount' => '10.00', 'reason' => 'Late delivery'])->assertCreated();

        $log = AuditLog::query()->where('action', 'finance.credit_note.issued')->firstOrFail();
        $this->assertSame('Late delivery', $log->reason);
        $this->assertSame($workspace->id, $log->workspace_id);
    }

    public function test_numbers_are_gap_free_and_listed_for_the_workspace_only(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        [$otherOwner, $otherWorkspace] = $this->owningWorkspace();
        $invoice = $this->invoiceOf($owner, $workspace, $this->subscriber($workspace), '100.00');
        $foreign = $this->invoiceOf($otherOwner, $otherWorkspace, $this->subscriber($otherWorkspace), '100.00');
        Sanctum::actingAs($owner);
        $this->twoFactor($owner);

        $one = $this->postJson($this->url($invoice), ['amount' => '10.00', 'reason' => 'One'])->json('data.number');
        $two = $this->postJson($this->url($invoice), ['amount' => '10.00', 'reason' => 'Two'])->json('data.number');
        $this->assertStringEndsWith('000001', $one);
        $this->assertStringEndsWith('000002', $two);
        $this->postJson($this->url($foreign), ['amount' => '10.00', 'reason' => 'Foreign'])->assertNotFound();

        $this->getJson(self::LIST)->assertOk()->assertJsonPath('data.meta.total', 2)->assertJsonCount(2, 'data.items');
        $this->getJson(self::LIST.'?invoice_id='.$invoice->id)->assertOk()->assertJsonPath('data.meta.total', 2);
    }

    public function test_issuing_needs_the_issue_void_permission_and_a_recent_two_factor_check(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $invoice = $this->invoiceOf($owner, $workspace, $this->subscriber($workspace), '100.00');
        $collector = $this->cashierFor($workspace, ['finance_collect']);
        $reporter = $this->cashierFor($workspace, ['finance_reports']);
        $issuer = $this->cashierFor($workspace, ['finance_issue_void']);

        Sanctum::actingAs($collector);
        $this->postJson($this->url($invoice), ['amount' => '1.00', 'reason' => 'Nope'])->assertForbidden();
        $this->getJson(self::LIST)->assertForbidden();

        Sanctum::actingAs($reporter);
        $this->getJson(self::LIST)->assertOk();
        $this->postJson($this->url($invoice), ['amount' => '1.00', 'reason' => 'Nope'])->assertForbidden();

        Sanctum::actingAs($issuer);
        $this->postJson($this->url($invoice), ['amount' => '1.00', 'reason' => 'No 2FA'])->assertStatus(403);
        $this->assertSame(0, CreditNote::query()->count());

        Sanctum::actingAs($owner);
        $this->postJson($this->url($invoice), ['amount' => '1.00', 'reason' => 'No 2FA'])->assertStatus(403);
    }

    public function test_the_endpoints_do_not_exist_while_finance_v2_is_off(): void
    {
        [$owner, $workspace] = $this->owningWorkspace(financeV2: false);
        $invoice = $this->invoiceOf($owner, $workspace, $this->subscriber($workspace), '100.00');
        Sanctum::actingAs($owner);
        $this->twoFactor($owner);

        $this->getJson(self::LIST)->assertNotFound();
        $this->postJson($this->url($invoice), ['amount' => '1.00', 'reason' => 'Off'])->assertNotFound();
    }

    public function test_the_pdf_downloads_for_the_workspace_only(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        [$otherOwner] = $this->owningWorkspace();
        $invoice = $this->invoiceOf($owner, $workspace, $this->subscriber($workspace), '100.00');
        Sanctum::actingAs($owner);
        $this->twoFactor($owner);
        $id = $this->postJson($this->url($invoice), ['amount' => '10.00', 'reason' => 'PDF'])->json('data.id');

        $this->get(self::LIST."/{$id}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');

        Sanctum::actingAs($otherOwner);
        $this->get(self::LIST."/{$id}/pdf")->assertNotFound();
    }

    private function url(\App\Models\Invoice $invoice): string
    {
        return "/api/workspace/invoices/{$invoice->id}/credit-notes";
    }

    private function twoFactor(\App\Models\User $user): void
    {
        $user->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()])->save();
        app(TwoFactorService::class)->markVerified($user);
    }
}
