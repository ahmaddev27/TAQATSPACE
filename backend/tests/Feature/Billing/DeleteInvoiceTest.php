<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Deleting an invoice also removes its ledger payments (FK cascade) and every
 * stored receipt file — the invoice's own and each payment's — from the media
 * disk (S3 in production).
 */
class DeleteInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_removes_the_invoice_payments_and_all_receipt_files(): void
    {
        Storage::fake('public');
        $disk = Storage::disk('public');
        $disk->put('receipts/inv/invoice.jpg', 'x');
        $disk->put('receipts/inv/payment.jpg', 'y');

        $invoice = Invoice::factory()->create(['receipt_path' => 'receipts/inv/invoice.jpg']);
        $invoice->payments()->create([
            'amount' => 10,
            'receipt_path' => 'receipts/inv/payment.jpg',
            'paid_at' => Carbon::now(),
        ]);

        app(InvoiceService::class)->delete($invoice);

        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
        $this->assertDatabaseMissing('invoice_payments', ['invoice_id' => $invoice->id]);
        $disk->assertMissing('receipts/inv/invoice.jpg');
        $disk->assertMissing('receipts/inv/payment.jpg');
    }

    public function test_delete_is_safe_when_there_are_no_receipts(): void
    {
        Storage::fake('public');
        $invoice = Invoice::factory()->create(['receipt_path' => null]);

        app(InvoiceService::class)->delete($invoice);

        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
    }
}
