<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\InvoiceStatus;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\InvoicePdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** The PDF templates are rendered to HTML and asserted on, mPDF output is not text-searchable. */
class CreditPdfViewTest extends TestCase
{
    use RefreshDatabase;

    /** @param  array<string, mixed>  $attributes */
    private function invoice(array $attributes): Invoice
    {
        $workspace = Workspace::factory()->create();
        $subscription = Subscription::factory()->create(['workspace_id' => $workspace->id]);

        return Invoice::factory()->create([...[
            'subscription_id' => $subscription->id,
            'workspace_id' => $workspace->id,
            'amount' => '100.00',
        ], ...$attributes])->load('subscription.workspace');
    }

    private function html(Invoice $invoice): string
    {
        return view('pdf.invoice', app(InvoicePdfService::class)->invoiceViewData($invoice))->render();
    }

    public function test_unpaid_invoice_shows_the_credit_and_the_remaining_after_it(): void
    {
        $html = $this->html($this->invoice(['credited_amount' => '30.00']));

        $this->assertStringContainsString('إشعارات دائنة', $html);
        $this->assertStringContainsString('30.00', $html);
        $this->assertStringContainsString('المتبقّي', $html);
        $this->assertStringContainsString('70.00', $html);
    }

    public function test_partly_paid_invoice_with_credit_adds_up(): void
    {
        $html = $this->html($this->invoice([
            'status' => InvoiceStatus::PartiallyPaid->value,
            'amount_paid' => '60.00',
            'credited_amount' => '20.00',
        ]));

        $this->assertStringContainsString('إشعارات دائنة', $html);
        $this->assertStringContainsString('60.00', $html);
        $this->assertStringContainsString('20.00', $html);
    }

    public function test_fully_credited_never_paid_invoice_is_settled_by_credit_note_not_paid(): void
    {
        $html = $this->html($this->invoice([
            'status' => InvoiceStatus::Paid->value,
            'paid_at' => Carbon::parse('2026-11-03'),
            'amount_paid' => '0.00',
            'credited_amount' => '100.00',
        ]));

        $this->assertStringContainsString('Settled by credit note', $html);
        $this->assertStringContainsString('مسوّاة بإشعار دائن', $html);
        $this->assertStringNotContainsString('Paid on', $html);
        $this->assertStringNotContainsString('تاريخ الدفع', $html);
        $this->assertStringNotContainsString('مدفوعة', $html);
    }

    public function test_really_paid_invoice_still_says_paid_on(): void
    {
        $html = $this->html($this->invoice([
            'status' => InvoiceStatus::Paid->value,
            'paid_at' => Carbon::parse('2026-11-03'),
            'amount_paid' => '100.00',
        ]));

        $this->assertStringContainsString('Paid on', $html);
        $this->assertStringNotContainsString('Settled by credit note', $html);
    }

    public function test_credit_note_shows_the_workspace_even_without_a_subscription(): void
    {
        $workspace = Workspace::factory()->create(['name' => 'Orbit Hub']);
        $customer = User::factory()->freelancer()->create();
        $invoice = Invoice::factory()->create([
            'subscription_id' => null,
            'workspace_id' => $workspace->id,
            'customer_id' => $customer->id,
            'amount' => '100.00',
        ]);
        $creditNote = CreditNote::query()->create([
            'workspace_id' => $workspace->id,
            'invoice_id' => $invoice->id,
            'number' => 'CN-1',
            'amount' => '10.00',
            'reason' => 'x',
            'issued_at' => now(),
        ]);

        $html = view('pdf.credit-note', ['creditNote' => $creditNote->load('workspace', 'invoice.customer')])->render();

        $this->assertStringContainsString('Orbit Hub', $html);
    }
}
