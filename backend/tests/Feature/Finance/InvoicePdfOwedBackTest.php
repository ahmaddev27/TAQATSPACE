<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Workspace;
use App\Services\InvoicePdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The totals block of the invoice PDF must add up in both languages: total,
 * credits, paid, remaining and, when the customer paid more than is payable,
 * what they are owed back. The template is rendered to HTML through the view
 * data InvoicePdfService builds, because mPDF output is not text-searchable.
 */
class InvoicePdfOwedBackTest extends TestCase
{
    use RefreshDatabase;

    private const SUBTOTAL = ['المجموع الفرعي', '100.00 ₪'];

    private const TOTAL = ['الإجمالي المستحق', '100.00 ₪'];

    private const OWED_BACK_AR = 'المبلغ المستحق للعميل';

    private const OWED_BACK_EN = 'Owed back';

    public function test_paid_then_credited_invoice_shows_what_is_owed_back(): void
    {
        $rows = $this->totals($this->invoice([
            'status' => InvoiceStatus::Paid->value,
            'paid_at' => Carbon::parse('2026-11-03'),
            'amount_paid' => '100.00',
            'credited_amount' => '30.00',
        ]));

        $this->assertSame([
            self::SUBTOTAL,
            self::TOTAL,
            ['إشعارات دائنة / Credit notes', '- 30.00 ₪'],
            ['المدفوع', '100.00 ₪'],
            ['المتبقّي', '0.00 ₪'],
            [self::OWED_BACK_AR.' / '.self::OWED_BACK_EN, '30.00 ₪'],
        ], $rows);
    }

    public function test_unpaid_credited_invoice_owes_nothing_back(): void
    {
        $rows = $this->totals($this->invoice(['credited_amount' => '30.00']));

        $this->assertSame([
            self::SUBTOTAL,
            self::TOTAL,
            ['إشعارات دائنة / Credit notes', '- 30.00 ₪'],
            ['المتبقّي', '70.00 ₪'],
        ], $rows);
    }

    public function test_partly_paid_credited_invoice_still_owes_the_rest_and_nothing_is_owed_back(): void
    {
        $rows = $this->totals($this->invoice([
            'status' => InvoiceStatus::PartiallyPaid->value,
            'amount_paid' => '60.00',
            'credited_amount' => '20.00',
        ]));

        $this->assertSame([
            self::SUBTOTAL,
            self::TOTAL,
            ['إشعارات دائنة / Credit notes', '- 20.00 ₪'],
            ['المدفوع', '60.00 ₪'],
            ['المتبقّي', '20.00 ₪'],
        ], $rows);
    }

    public function test_overpaid_invoice_without_credit_shows_the_overpayment_as_owed_back(): void
    {
        $rows = $this->totals($this->invoice([
            'status' => InvoiceStatus::Paid->value,
            'paid_at' => Carbon::parse('2026-11-03'),
            'amount_paid' => '140.00',
        ]));

        $this->assertSame([
            self::SUBTOTAL,
            self::TOTAL,
            ['المدفوع', '140.00 ₪'],
            ['المتبقّي', '0.00 ₪'],
            [self::OWED_BACK_AR.' / '.self::OWED_BACK_EN, '40.00 ₪'],
        ], $rows);
    }

    public function test_fully_credited_never_paid_invoice_is_settled_by_credit_with_nothing_owed_back(): void
    {
        $html = $this->html($this->invoice([
            'status' => InvoiceStatus::Paid->value,
            'paid_at' => Carbon::parse('2026-11-03'),
            'amount_paid' => '0.00',
            'credited_amount' => '100.00',
        ]));

        $this->assertSame([
            self::SUBTOTAL,
            self::TOTAL,
            ['إشعارات دائنة / Credit notes', '- 100.00 ₪'],
            ['المتبقّي', '0.00 ₪'],
        ], $this->totalsRows($html));
        $this->assertStringContainsString('Settled by credit note', $html);
        $this->assertStringContainsString('مسوّاة بإشعار دائن', $html);
        $this->assertNoOwedBack($html);
    }

    /**
     * Without credits or an overpayment the block keeps its rows: just the
     * totals when unpaid or paid in full, paid and remaining when partly paid.
     *
     * @return array<string, array{array<string, mixed>, list<array{string, string}>}>
     */
    public static function ordinaryInvoices(): array
    {
        return [
            'unpaid' => [[], [self::SUBTOTAL, self::TOTAL]],
            'paid in full' => [
                ['status' => InvoiceStatus::Paid->value, 'paid_at' => '2026-11-03', 'amount_paid' => '100.00'],
                [self::SUBTOTAL, self::TOTAL],
            ],
            'legacy paid with amount_paid 0' => [
                ['status' => InvoiceStatus::Paid->value, 'paid_at' => '2026-11-03', 'amount_paid' => '0.00'],
                [self::SUBTOTAL, self::TOTAL],
            ],
            'partly paid' => [
                ['status' => InvoiceStatus::PartiallyPaid->value, 'amount_paid' => '40.00'],
                [self::SUBTOTAL, self::TOTAL, ['المدفوع', '40.00 ₪'], ['المتبقّي', '60.00 ₪']],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array{string, string}>  $expected
     */
    #[DataProvider('ordinaryInvoices')]
    public function test_ordinary_invoice_totals_are_unchanged(array $attributes, array $expected): void
    {
        $html = $this->html($this->invoice($attributes));

        $this->assertSame($expected, $this->totalsRows($html));
        $this->assertNoOwedBack($html);
        $this->assertStringNotContainsString('Credit notes', $html);
    }

    public function test_the_real_pdf_renders_the_owed_back_invoice_under_a_new_cache_version(): void
    {
        Storage::fake();
        $invoice = $this->invoice([
            'status' => InvoiceStatus::Paid->value,
            'paid_at' => Carbon::parse('2026-11-03'),
            'amount_paid' => '100.00',
            'credited_amount' => '30.00',
        ]);

        $path = app(InvoicePdfService::class)->ensureGenerated($invoice);

        // A PDF cached by the previous template would lack the owed-back row.
        $this->assertStringStartsWith('invoices/v7/', $path);
        $this->assertStringStartsWith('%PDF', (string) Storage::get($path));
    }

    /** @param  array<string, mixed>  $attributes */
    private function invoice(array $attributes): Invoice
    {
        $workspace = Workspace::factory()->create();
        // A fixed price keeps the subtotal row deterministic (the factory picks one at random).
        $subscription = Subscription::factory()->create(['workspace_id' => $workspace->id, 'monthly_price' => '100.00']);

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

    /** @return list<array{string, string}> */
    private function totals(Invoice $invoice): array
    {
        return $this->totalsRows($this->html($invoice));
    }

    /**
     * Each totals row as [label, value], with the English sub-label joined by
     * " / ", so a test reads the whole block the way a customer does.
     *
     * @return list<array{string, string}>
     */
    private function totalsRows(string $html): array
    {
        $this->assertSame(1, preg_match('/<table class="totals">(.*?)<\/table>/s', $html, $table));
        preg_match_all('/<td class="t-label"[^>]*>(.*?)<\/td>\s*<td class="t-value"[^>]*>(.*?)<\/td>/s', $table[1], $cells, PREG_SET_ORDER);

        return array_map(fn (array $cell): array => [$this->text($cell[1]), $this->text($cell[2])], $cells);
    }

    private function text(string $fragment): string
    {
        $text = html_entity_decode(strip_tags(str_replace('<div class="en">', ' / ', $fragment)));

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private function assertNoOwedBack(string $html): void
    {
        $this->assertStringNotContainsString(self::OWED_BACK_AR, $html);
        $this->assertStringNotContainsString(self::OWED_BACK_EN, $html);
    }
}
