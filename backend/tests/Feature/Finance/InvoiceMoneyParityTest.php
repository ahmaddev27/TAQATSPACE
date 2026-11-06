<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\DocStatus;
use App\Enums\InvoiceStatus;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Services\Finance\InvoiceBalanceService;
use App\Support\Finance\InvoiceMoney;
use Illuminate\Database\Query\Expression;
use Illuminate\Http\Request;

/**
 * One definition of what an invoice still owes, what is owed back and what
 * was collected: the PHP helpers, the SQL the reports sum, the balance service
 * and the API resource must agree on every case.
 */
class InvoiceMoneyParityTest extends ReceiptTestCase
{
    /**
     * amount, credited, paid, legacy status, doc status => still owed, owed back, collected.
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: InvoiceStatus, 4: DocStatus|null, 5: string, 6: string, 7: string}>
     */
    private const CASES = [
        'unpaid' => ['100.00', '0.00', '0.00', InvoiceStatus::Pending, null, '100.00', '0.00', '0.00'],
        'partial payment' => ['100.00', '0.00', '40.00', InvoiceStatus::PartiallyPaid, null, '60.00', '0.00', '40.00'],
        'credit and partial payment' => ['100.00', '30.00', '50.00', InvoiceStatus::PartiallyPaid, null, '20.00', '0.00', '50.00'],
        'credit after a full payment' => ['100.00', '30.00', '100.00', InvoiceStatus::Paid, null, '0.00', '30.00', '70.00'],
        'fully credited, never paid' => ['100.00', '100.00', '0.00', InvoiceStatus::Paid, null, '0.00', '0.00', '0.00'],
        'overpayment without a credit' => ['60.00', '0.00', '90.00', InvoiceStatus::Paid, null, '0.00', '30.00', '60.00'],
        'paid exactly' => ['80.00', '0.00', '80.00', InvoiceStatus::Paid, null, '0.00', '0.00', '80.00'],
        'void' => ['100.00', '0.00', '0.00', InvoiceStatus::Cancelled, DocStatus::Void, '100.00', '0.00', '0.00'],
        'draft' => ['100.00', '0.00', '0.00', InvoiceStatus::Pending, DocStatus::Draft, '100.00', '0.00', '0.00'],
    ];

    public function test_php_and_sql_agree_on_every_case(): void
    {
        $balances = app(InvoiceBalanceService::class);

        foreach ($this->seedCases() as $case => $invoice) {
            [, , , , $doc, $owed, $owedBack, $collected] = self::CASES[$case];
            $sql = $this->sqlFigures($invoice);

            $this->assertSame($owed, InvoiceMoney::stillOwed($invoice), "{$case}: still owed");
            $this->assertSame($owedBack, InvoiceMoney::owedBack($invoice), "{$case}: owed back");
            $this->assertSame($collected, InvoiceMoney::collected($invoice), "{$case}: collected");

            $this->assertSame($owed, $sql['owed'], "{$case}: SQL still owed");
            $this->assertSame($collected, $sql['collected'], "{$case}: SQL collected");
            $this->assertSame(bcsub((string) $invoice->amount_paid, $collected, 2), $owedBack, "{$case}: owed back is paid less collected");

            $this->assertSame($owed, $balances->unpaid($invoice), "{$case}: balance service still owed");
            $this->assertSame($owedBack, $balances->refundDue($invoice), "{$case}: balance service owed back");
            $this->assertEquals((float) $owed, InvoiceMoney::receivable($invoice), "{$case}: float twin");

            $remaining = (new InvoiceResource($invoice))->toArray(Request::create('/'))['remaining_amount'];
            $this->assertSame($doc === DocStatus::Void ? '0.00' : $owed, $remaining, "{$case}: API remaining");
        }
    }

    public function test_the_sums_skip_void_draft_and_settled_invoices(): void
    {
        $invoices = $this->seedCases();

        $receivable = InvoiceMoney::sumReceivable(Invoice::query()->notDraft()->receivable());
        $collected = (float) Invoice::query()->sum(new Expression(InvoiceMoney::collectedSql()));

        // Unpaid 100 + partial 60 + credit and partial 20.
        $this->assertEquals(180, $receivable);
        $this->assertEquals(
            array_sum(array_map(static fn (Invoice $invoice): float => (float) InvoiceMoney::collected($invoice), $invoices)),
            $collected,
        );
        $this->assertEquals(300, $collected, 'The overpayment and the credited part are owed back, not collected.');
    }

    /** @return array<string, Invoice> */
    private function seedCases(): array
    {
        [, $workspace] = $this->owningWorkspace();
        $subscription = Subscription::factory()->create(['workspace_id' => $workspace->id]);
        $invoices = [];

        foreach (self::CASES as $case => [$amount, $credited, $paid, $status, $doc]) {
            $invoices[$case] = Invoice::factory()->create(array_filter([
                'subscription_id' => $subscription->id,
                'amount' => $amount,
                'credited_amount' => $credited,
                'amount_paid' => $paid,
                'status' => $status,
                'doc_status' => $doc,
            ], static fn (mixed $value): bool => $value !== null))->fresh();
        }

        return $invoices;
    }

    /** @return array{owed: string, collected: string} */
    private function sqlFigures(Invoice $invoice): array
    {
        $row = Invoice::query()->whereKey($invoice->id)->toBase()
            ->selectRaw(InvoiceMoney::receivableSql().' as owed, '.InvoiceMoney::collectedSql().' as collected')
            ->first();

        return ['owed' => bcadd((string) $row->owed, '0', 2), 'collected' => bcadd((string) $row->collected, '0', 2)];
    }
}
