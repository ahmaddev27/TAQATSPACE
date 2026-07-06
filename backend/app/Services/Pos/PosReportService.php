<?php

declare(strict_types=1);

namespace App\Services\Pos;

use App\Enums\PosOrderStatus;
use App\Models\PosOrder;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * POS sales reporting for a workspace over a date range. Every figure is derived
 * from PAID, non-refunded orders (settled within the range), with a separate
 * refunds tally. Aggregation is done in PHP (via collections) rather than with
 * SQL date functions, so the same queries run on both MySQL and the sqlite test
 * database. Money is emitted as fixed 2-decimal strings, matching the owner
 * reports contract.
 */
class PosReportService
{
    /** Default trailing window (in days) when no explicit range is given. */
    private const DEFAULT_DAYS = 30;

    /** How many best-selling products the report surfaces. */
    private const TOP_PRODUCTS = 8;

    /**
     * @return array{
     *     range: array{from: string, to: string},
     *     totals: array{sales: string, orders: int, avg: string},
     *     top_products: array<int, array{name: string, qty: int, total: string}>,
     *     by_cashier: array<int, array{name: string, orders: int, total: string}>,
     *     by_method: array<int, array{method: string, total: string}>,
     *     refunds: array{count: int, total: string}
     * }
     */
    public function build(Workspace $workspace, ?string $from, ?string $to): array
    {
        [$start, $end] = $this->resolveRange($from, $to);

        return [
            'range' => ['from' => $start->toDateString(), 'to' => $end->toDateString()],
            'totals' => $this->totals($workspace, $start, $end),
            'top_products' => $this->topProducts($workspace, $start, $end),
            'by_cashier' => $this->byCashier($workspace, $start, $end),
            'by_method' => $this->byMethod($workspace, $start, $end),
            'refunds' => $this->refunds($workspace, $start, $end),
        ];
    }

    /**
     * @return array{sales: string, orders: int, avg: string}
     */
    private function totals(Workspace $workspace, Carbon $start, Carbon $end): array
    {
        $query = $this->paidOrders($workspace, $start, $end);

        $sales = (float) (clone $query)->sum('total');
        $orders = (clone $query)->count();

        return [
            'sales' => $this->money($sales),
            'orders' => $orders,
            'avg' => $this->money($orders > 0 ? $sales / $orders : 0.0),
        ];
    }

    /**
     * Top sellers by quantity, grouped by the snapshotted line name so a later
     * product rename never splits a product's history.
     *
     * @return array<int, array{name: string, qty: int, total: string}>
     */
    private function topProducts(Workspace $workspace, Carbon $start, Carbon $end): array
    {
        $rows = $this->paidOrders($workspace, $start, $end)
            ->join('pos_order_items', 'pos_order_items.pos_order_id', '=', 'pos_orders.id')
            ->get(['pos_order_items.name', 'pos_order_items.qty', 'pos_order_items.line_total']);

        return $rows
            ->groupBy('name')
            ->map(static fn (Collection $lines, string $name): array => [
                'name' => $name,
                'qty' => (int) $lines->sum('qty'),
                'total' => number_format((float) $lines->sum('line_total'), 2, '.', ''),
            ])
            ->sortByDesc('qty')
            ->take(self::TOP_PRODUCTS)
            ->values()
            ->all();
    }

    /**
     * Sales split by the cashier who rang them up; walk-in / unattributed orders
     * fall under a single "—" bucket.
     *
     * @return array<int, array{name: string, orders: int, total: string}>
     */
    private function byCashier(Workspace $workspace, Carbon $start, Carbon $end): array
    {
        $rows = $this->paidOrders($workspace, $start, $end)
            ->leftJoin('users', 'users.id', '=', 'pos_orders.cashier_id')
            ->get(['pos_orders.cashier_id', 'users.name as cashier_name', 'pos_orders.total']);

        return $rows
            ->groupBy(static fn (object $row): string => $row->cashier_id ?? 'walk-in')
            ->map(static fn (Collection $group): array => [
                'name' => $group->first()->cashier_name ?? '—',
                'orders' => $group->count(),
                'total' => number_format((float) $group->sum('total'), 2, '.', ''),
            ])
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /**
     * Revenue split by payment method (cash / transfer). Refunded orders are
     * excluded upstream, so only the settling positive payments are summed.
     *
     * @return array<int, array{method: string, total: string}>
     */
    private function byMethod(Workspace $workspace, Carbon $start, Carbon $end): array
    {
        $rows = $this->paidOrders($workspace, $start, $end)
            ->join('pos_payments', 'pos_payments.pos_order_id', '=', 'pos_orders.id')
            ->get(['pos_payments.method', 'pos_payments.amount']);

        return $rows
            ->groupBy('method')
            ->map(static fn (Collection $group, string $method): array => [
                'method' => $method,
                'total' => number_format((float) $group->sum('amount'), 2, '.', ''),
            ])
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /**
     * Refunds reversed within the range, tallied separately from net sales.
     *
     * @return array{count: int, total: string}
     */
    private function refunds(Workspace $workspace, Carbon $start, Carbon $end): array
    {
        $query = PosOrder::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', PosOrderStatus::Refunded->value)
            ->whereNotNull('refunded_at')
            ->whereBetween('refunded_at', [$start, $end]);

        return [
            'count' => (clone $query)->count(),
            'total' => $this->money((float) (clone $query)->sum('total')),
        ];
    }

    /**
     * Base query for the orders every sales figure is built on: this workspace's
     * PAID, non-refunded orders settled within the range.
     *
     * @return Builder<PosOrder>
     */
    private function paidOrders(Workspace $workspace, Carbon $start, Carbon $end): Builder
    {
        return PosOrder::query()
            ->where('pos_orders.workspace_id', $workspace->id)
            ->where('pos_orders.status', '!=', PosOrderStatus::Refunded->value)
            ->whereNotNull('pos_orders.paid_at')
            ->whereBetween('pos_orders.paid_at', [$start, $end]);
    }

    /**
     * Resolve the reporting window, defaulting to the trailing 30 days. Invalid
     * input falls back to the default rather than erroring.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(?string $from, ?string $to): array
    {
        $end = $this->parseDate($to)?->endOfDay() ?? Carbon::now()->endOfDay();
        $start = $this->parseDate($from)?->startOfDay()
            ?? $end->copy()->subDays(self::DEFAULT_DAYS - 1)->startOfDay();

        // Guard against an inverted range (from after to).
        if ($start->greaterThan($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        return [$start, $end];
    }

    private function parseDate(?string $value): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
