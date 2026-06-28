<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class RecordPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function service(): InvoiceService
    {
        return app(InvoiceService::class);
    }

    private function invoice(string $amount): Invoice
    {
        return Invoice::factory()->create([
            'amount' => $amount,
            'amount_paid' => 0,
            'status' => InvoiceStatus::Pending->value,
        ]);
    }

    public function test_partial_then_final_payment_moves_pending_to_paid(): void
    {
        Notification::fake();
        $invoice = $this->invoice('50.00');

        $this->service()->recordPartialPayment($invoice, 20);
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::PartiallyPaid, $invoice->status);
        $this->assertSame('20.00', (string) $invoice->amount_paid);
        $this->assertNull($invoice->paid_at);

        $this->service()->recordPartialPayment($invoice, 30);
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame('50.00', (string) $invoice->amount_paid);
        $this->assertNotNull($invoice->paid_at);
    }

    public function test_a_zero_or_negative_amount_is_rejected(): void
    {
        $invoice = $this->invoice('50.00');

        $this->expectException(RuntimeException::class);
        $this->service()->recordPartialPayment($invoice, 0);
    }

    public function test_an_already_paid_invoice_rejects_further_payment(): void
    {
        Notification::fake();
        $invoice = $this->invoice('40.00');
        $this->service()->recordPartialPayment($invoice, 40);

        $this->expectException(RuntimeException::class);
        $this->service()->recordPartialPayment($invoice->refresh(), 10);
    }

    public function test_a_payment_above_the_remaining_balance_is_rejected(): void
    {
        Notification::fake();
        $invoice = $this->invoice('100.00');
        $this->service()->recordPartialPayment($invoice, 70);

        // Only 30 remains; a 40 payment must not be accepted.
        $this->expectException(RuntimeException::class);
        $this->service()->recordPartialPayment($invoice->refresh(), 40);
    }

    public function test_each_installment_appends_one_row_to_the_ledger(): void
    {
        Notification::fake();
        $invoice = $this->invoice('100.00');

        $this->service()->recordPartialPayment($invoice, 60);
        $this->service()->recordPartialPayment($invoice->refresh(), 40);

        $payments = $invoice->refresh()->payments()->orderBy('id')->get();
        $this->assertCount(2, $payments);
        $this->assertSame('60.00', (string) $payments[0]->amount);
        $this->assertSame('40.00', (string) $payments[1]->amount);
    }
}
