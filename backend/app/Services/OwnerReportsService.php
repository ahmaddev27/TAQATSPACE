<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Expense;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Workspace;
use Illuminate\Support\Carbon;

/**
 * Owner reporting: collections aging, a monthly profit-and-loss (revenue vs
 * expenses), and internet-package uptake — the deeper figures behind the owner
 * reports page.
 */
class OwnerReportsService
{
    /** How many trailing months the P&L spans (including the current month). */
    private const PL_MONTHS = 6;

    /**
     * @return array{
     *     aging: array<int, array{key: string, count: int, amount: string}>,
     *     aging_total: string,
     *     profit_loss: array<int, array{month: string, revenue: string, expenses: string, net: string}>,
     *     package_uptake: array<int, array{name: string, price: string, members: int}>
     * }
     */
    public function build(Workspace $workspace): array
    {
        return [
            'aging' => $this->aging($workspace),
            'aging_total' => $this->agingTotal,
            'profit_loss' => $this->profitLoss($workspace),
            'package_uptake' => $this->packageUptake($workspace),
        ];
    }

    private string $agingTotal = '0.00';

    /**
     * Outstanding (unpaid/partly-paid) invoice balances, bucketed by how far past
     * the due date they are — so the owner can chase the oldest debt first.
     *
     * @return array<int, array{key: string, count: int, amount: string}>
     */
    private function aging(Workspace $workspace): array
    {
        $today = Carbon::today();

        $invoices = Invoice::query()
            ->forWorkspace($workspace->id)
            ->whereIn('status', [
                InvoiceStatus::Pending->value,
                InvoiceStatus::Overdue->value,
                InvoiceStatus::PartiallyPaid->value,
            ])
            ->whereNotNull('due_date')
            ->get(['amount', 'amount_paid', 'due_date']);

        $buckets = [
            'current' => ['count' => 0, 'amount' => 0.0],
            'd1_30' => ['count' => 0, 'amount' => 0.0],
            'd31_60' => ['count' => 0, 'amount' => 0.0],
            'd61_90' => ['count' => 0, 'amount' => 0.0],
            'd90_plus' => ['count' => 0, 'amount' => 0.0],
        ];

        $total = 0.0;

        foreach ($invoices as $invoice) {
            $outstanding = max(0.0, (float) $invoice->amount - (float) $invoice->amount_paid);

            if ($outstanding <= 0) {
                continue;
            }

            $total += $outstanding;
            $dueDate = Carbon::parse($invoice->due_date);
            $daysOverdue = $dueDate->isBefore($today) ? $dueDate->diffInDays($today) : 0;

            $key = match (true) {
                $daysOverdue <= 0 => 'current',
                $daysOverdue <= 30 => 'd1_30',
                $daysOverdue <= 60 => 'd31_60',
                $daysOverdue <= 90 => 'd61_90',
                default => 'd90_plus',
            };

            $buckets[$key]['count']++;
            $buckets[$key]['amount'] += $outstanding;
        }

        $this->agingTotal = $this->money($total);

        return array_map(
            static fn (string $key, array $b): array => [
                'key' => $key,
                'count' => $b['count'],
                'amount' => number_format($b['amount'], 2, '.', ''),
            ],
            array_keys($buckets),
            array_values($buckets),
        );
    }

    /**
     * Monthly revenue (payments collected) vs expenses for the trailing months.
     *
     * @return array<int, array{month: string, revenue: string, expenses: string, net: string}>
     */
    private function profitLoss(Workspace $workspace): array
    {
        $start = Carbon::today()->startOfMonth()->subMonths(self::PL_MONTHS - 1);

        // Revenue: amount actually collected, keyed by the month it was paid.
        $revenueByMonth = Invoice::query()
            ->forWorkspace($workspace->id)
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $start)
            ->selectRaw("DATE_FORMAT(paid_at, '%Y-%m') as ym, SUM(amount_paid) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $expensesByMonth = Expense::query()
            ->where('workspace_id', $workspace->id)
            ->where('spent_on', '>=', $start->toDateString())
            ->selectRaw("DATE_FORMAT(spent_on, '%Y-%m') as ym, SUM(amount) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $rows = [];

        for ($i = 0; $i < self::PL_MONTHS; $i++) {
            $month = $start->copy()->addMonths($i)->format('Y-m');
            $revenue = (float) ($revenueByMonth[$month] ?? 0);
            $expenses = (float) ($expensesByMonth[$month] ?? 0);

            $rows[] = [
                'month' => $month,
                'revenue' => $this->money($revenue),
                'expenses' => $this->money($expenses),
                'net' => $this->money($revenue - $expenses),
            ];
        }

        return $rows;
    }

    /**
     * Internet packages with how many members each is assigned to.
     *
     * @return array<int, array{name: string, price: string, members: int}>
     */
    private function packageUptake(Workspace $workspace): array
    {
        return InternetPackage::query()
            ->where('workspace_id', $workspace->id)
            ->withCount('members')
            ->orderByDesc('members_count')
            ->get()
            ->map(static fn (InternetPackage $p): array => [
                'name' => $p->name,
                'price' => number_format((float) $p->price, 2, '.', ''),
                'members' => (int) $p->members_count,
            ])
            ->all();
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
