<?php

declare(strict_types=1);

namespace App\Services\Demo\Steps;

use App\Enums\ExpenseCategory;
use App\Enums\InvoiceStatus;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Subscription;
use App\Services\Demo\DemoContext;
use App\Services\Demo\DemoSeedStep;

/** Invoices in every status (with a partial payment) and workspace expenses. */
class FinanceStep implements DemoSeedStep
{
    private const AMOUNT = 40;

    /** @var list<array{0: string, 1: InvoiceStatus}> member key and the invoice status to give it */
    private const INVOICES = [
        ['freelancer1', InvoiceStatus::Paid],
        ['freelancer1', InvoiceStatus::PartiallyPaid],
        ['freelancer1', InvoiceStatus::Pending],
        ['freelancer2', InvoiceStatus::Overdue],
        ['freelancer2', InvoiceStatus::UnderReview],
        ['freelancer2', InvoiceStatus::PaymentRejected],
        ['freelancer4', InvoiceStatus::Cancelled],
        ['freelancer7', InvoiceStatus::Pending],
        ['freelancer7', InvoiceStatus::Paid],
    ];

    public function seed(DemoContext $context): void
    {
        $this->seedInvoices($context);
        $this->seedExpenses($context);
    }

    private function seedInvoices(DemoContext $context): void
    {
        $subscriptions = Subscription::query()
            ->whereIn('member_id', collect($context->users())->map->id->values())
            ->get()
            ->keyBy('member_id');

        foreach (self::INVOICES as $index => [$userKey, $status]) {
            $subscription = $subscriptions[$context->user($userKey)->id];
            $invoice = Invoice::query()->create($this->invoiceAttributes($subscription, $status, $index + 1));

            if ($status === InvoiceStatus::PartiallyPaid) {
                InvoicePayment::query()->create([
                    'invoice_id' => $invoice->id,
                    'amount' => $invoice->amount_paid,
                    'paid_at' => now()->subDays(5),
                ]);
            }
        }
    }

    /** @return array<string, mixed> */
    private function invoiceAttributes(Subscription $subscription, InvoiceStatus $status, int $sequence): array
    {
        $overdue = $status === InvoiceStatus::Overdue;
        $paid = $status === InvoiceStatus::Paid;

        return [
            'subscription_id' => $subscription->id,
            'amount' => self::AMOUNT,
            'amount_paid' => match ($status) {
                InvoiceStatus::Paid => self::AMOUNT,
                InvoiceStatus::PartiallyPaid => 15,
                default => 0,
            },
            'due_date' => ($overdue ? now()->subWeeks(3) : now()->addWeeks(2))->toDateString(),
            'paid_at' => $paid ? now()->subDays(10) : null,
            'status' => $status,
            'invoice_number' => sprintf('DEMO-%04d', $sequence),
            'receipt_path' => $status === InvoiceStatus::UnderReview ? 'receipts/demo/receipt.pdf' : null,
            'receipt_rejected_reason' => $status === InvoiceStatus::PaymentRejected ? 'الإيصال غير واضح.' : null,
        ];
    }

    private function seedExpenses(DemoContext $context): void
    {
        $expenses = [
            ['ws1', 'إيجار المكتب', ExpenseCategory::Rent, 1200],
            ['ws1', 'فاتورة الكهرباء', ExpenseCategory::Utilities, 310.5],
            ['ws1', 'رواتب الموظفين', ExpenseCategory::Salaries, 2500],
            ['ws1', 'صيانة المكيفات', ExpenseCategory::Maintenance, 180],
            ['ws1', 'مستلزمات مكتبية', ExpenseCategory::Supplies, 95.25],
            ['ws2', 'حملة تسويقية', ExpenseCategory::Marketing, 400],
            ['ws2', 'إيجار المكتب', ExpenseCategory::Rent, 900],
        ];

        foreach ($expenses as $offset => [$workspaceKey, $title, $category, $amount]) {
            Expense::query()->create([
                'workspace_id' => $context->workspace($workspaceKey)->id,
                'title' => $title,
                'category' => $category,
                'amount' => $amount,
                'spent_on' => now()->subDays($offset * 4 + 1)->toDateString(),
            ]);
        }
    }
}
