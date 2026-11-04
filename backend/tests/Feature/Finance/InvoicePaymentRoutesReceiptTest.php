<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\InvoiceStatus;
use App\Models\Receipt;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/** The legacy owner payment routes under finance v2: same answers, but backed by receipts. */
class InvoicePaymentRoutesReceiptTest extends ReceiptTestCase
{
    private const INVOICES = '/api/workspace/invoices';

    public function test_a_partial_payment_creates_a_linked_receipt_and_keeps_the_legacy_row(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $invoice = $this->invoiceOf($owner, $workspace, $this->subscriber($workspace), '100.00');
        Sanctum::actingAs($owner);

        $this->putJson(self::INVOICES."/{$invoice->id}/partial-payment", ['amount' => 40])->assertOk()
            ->assertJsonPath('data.status', 'partially_paid');
        $this->putJson(self::INVOICES."/{$invoice->id}/partial-payment", ['amount' => 60])->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $this->assertSame(2, $invoice->payments()->count());
        $receipts = Receipt::query()->orderBy('number')->get();
        $this->assertCount(2, $receipts);
        $this->assertEqualsCanonicalizing($invoice->payments()->pluck('id')->all(), $receipts->pluck('legacy_payment_id')->all());
        $this->assertEquals(100, $invoice->fresh()->amount_paid);
        $this->putJson(self::INVOICES."/{$invoice->id}/partial-payment", ['amount' => 1])->assertUnprocessable();
    }

    public function test_a_payment_with_proof_stores_the_file_privately(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        [$owner, $workspace] = $this->owningWorkspace();
        $invoice = $this->invoiceOf($owner, $workspace, $this->subscriber($workspace), '100.00');
        Sanctum::actingAs($owner);

        $this->post(self::INVOICES."/{$invoice->id}/payment", ['amount' => 100, 'receipt' => UploadedFile::fake()->image('p.png')], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('data.status', 'paid');

        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->assertNotNull(Receipt::query()->firstOrFail()->receipt_path);

        $list = $this->getJson(self::INVOICES)->assertOk();
        $this->assertStringContainsString('signature=', (string) $list->json('data.invoices.0.payments.0.receipt_url'));
    }

    public function test_voiding_the_receipt_takes_the_legacy_payment_back_too(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $invoice = $this->invoiceOf($owner, $workspace, $this->subscriber($workspace), '100.00');
        Sanctum::actingAs($owner);
        $this->putJson(self::INVOICES."/{$invoice->id}/partial-payment", ['amount' => 100])->assertOk();

        app(\App\Services\Finance\ReceiptService::class)->void(Receipt::query()->firstOrFail(), 'Wrong', $owner);

        $this->assertSame(0, $invoice->payments()->count());
        $this->assertSame(InvoiceStatus::Pending, $invoice->fresh()->status);
    }

    public function test_draft_and_void_invoices_still_refuse_payments(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $draft = $this->draftFor($owner, $workspace);
        Sanctum::actingAs($owner);

        $this->putJson(self::INVOICES."/{$draft->id}/partial-payment", ['amount' => 5])->assertStatus(409);
        $this->assertSame(0, Receipt::query()->count());
    }

    public function test_approving_a_submitted_receipt_settles_the_invoice_through_a_receipt(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $invoice = $this->invoiceOf($owner, $workspace, $this->subscriber($workspace), '100.00');
        $invoice->forceFill(['status' => InvoiceStatus::UnderReview, 'receipt_path' => 'receipts/x/proof.pdf'])->save();
        Sanctum::actingAs($owner);

        $this->putJson(self::INVOICES."/{$invoice->id}/approve-receipt")->assertOk()->assertJsonPath('data.status', 'paid');

        $receipt = Receipt::query()->firstOrFail();
        $this->assertEquals(100, $receipt->amount);
        $this->assertSame('receipts/x/proof.pdf', $receipt->receipt_path);
    }
}
