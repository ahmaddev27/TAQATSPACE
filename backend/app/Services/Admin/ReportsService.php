<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Enums\WorkspaceStatus;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Operational/financial tracking aggregates for the super-admin reports view.
 *
 * Every series is computed with a single grouped query (MySQL DATE_FORMAT for
 * month buckets), never a loop over rows, so the whole payload costs a handful
 * of indexed aggregate queries. "Revenue" here is admin-tracked bookkeeping,
 * not gateway money: there is no payment processor in this business model.
 */
class ReportsService
{
    /** Length of the trailing month window for every time-series section. */
    private const MONTHS = 12;

    /**
     * @return array{
     *     revenue_by_month: array<int, array{month: string, paid: string, outstanding: string}>,
     *     invoices_by_status: array{
     *         paid: array{count: int, amount: string},
     *         pending: array{count: int, amount: string},
     *         overdue: array{count: int, amount: string}
     *     },
     *     subscriptions_by_status: array<string, int>,
     *     subscriptions_by_month: array<int, array{month: string, count: int}>,
     *     top_workspaces: array<int, array{workspace_id: string, name: string, paid_total: string, active_subscriptions: int}>,
     *     workspaces_by_status: array<string, int>,
     *     users_by_role: array<string, int>
     * }
     */
    public function build(): array
    {
        return [
            'revenue_by_month' => $this->revenueByMonth(),
            'invoices_by_status' => $this->invoicesByStatus(),
            'subscriptions_by_status' => $this->subscriptionsByStatus(),
            'subscriptions_by_month' => $this->subscriptionsByMonth(),
            'top_workspaces' => $this->topWorkspaces(),
            'workspaces_by_status' => $this->workspacesByStatus(),
            'users_by_role' => $this->usersByRole(),
        ];
    }

    /**
     * Continuous last-12-months revenue series. `paid` sums paid invoices by the
     * month they were paid (paid_at); `outstanding` sums pending + overdue
     * invoices by the month they are due (due_date). Missing months are filled
     * with zeros in PHP so the series is always contiguous.
     *
     * @return array<int, array{month: string, paid: string, outstanding: string}>
     */
    private function revenueByMonth(): array
    {
        $months = $this->monthWindow();
        $start = $months->first().'-01';

        $paid = Invoice::query()
            ->where('status', InvoiceStatus::Paid->value)
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $start)
            ->selectRaw("DATE_FORMAT(paid_at, '%Y-%m') as bucket, SUM(amount) as total")
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        $outstanding = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Pending->value, InvoiceStatus::Overdue->value])
            ->where('due_date', '>=', $start)
            ->selectRaw("DATE_FORMAT(due_date, '%Y-%m') as bucket, SUM(amount) as total")
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        return $months
            ->map(fn (string $month): array => [
                'month' => $month,
                'paid' => $this->money($paid->get($month)),
                'outstanding' => $this->money($outstanding->get($month)),
            ])
            ->all();
    }

    /**
     * Count + amount sum per invoice status (paid / pending / overdue).
     *
     * @return array{
     *     paid: array{count: int, amount: string},
     *     pending: array{count: int, amount: string},
     *     overdue: array{count: int, amount: string}
     * }
     */
    private function invoicesByStatus(): array
    {
        $rows = Invoice::query()
            ->selectRaw('status, COUNT(*) as aggregate, SUM(amount) as total')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $bucket = static fn (InvoiceStatus $status): array => [
            'count' => (int) ($rows->get($status->value)->aggregate ?? 0),
            'amount' => number_format((float) ($rows->get($status->value)->total ?? 0), 2, '.', ''),
        ];

        return [
            'paid' => $bucket(InvoiceStatus::Paid),
            'pending' => $bucket(InvoiceStatus::Pending),
            'overdue' => $bucket(InvoiceStatus::Overdue),
        ];
    }

    /**
     * Count per SubscriptionStatus, with every status present (zero-filled).
     *
     * @return array<string, int>
     */
    private function subscriptionsByStatus(): array
    {
        $counts = $this->countsByColumn(Subscription::query()->getModel(), 'status');

        $result = [];
        foreach (SubscriptionStatus::cases() as $status) {
            $result[$status->value] = (int) $counts->get($status->value, 0);
        }

        return $result;
    }

    /**
     * Continuous last-12-months count of new subscriptions, bucketed by
     * start_date. Missing months are zero-filled.
     *
     * @return array<int, array{month: string, count: int}>
     */
    private function subscriptionsByMonth(): array
    {
        $months = $this->monthWindow();

        $counts = Subscription::query()
            ->where('start_date', '>=', $months->first().'-01')
            ->selectRaw("DATE_FORMAT(start_date, '%Y-%m') as bucket, COUNT(*) as aggregate")
            ->groupBy('bucket')
            ->pluck('aggregate', 'bucket');

        return $months
            ->map(fn (string $month): array => [
                'month' => $month,
                'count' => (int) $counts->get($month, 0),
            ])
            ->all();
    }

    /**
     * Top 10 workspaces by total PAID invoice amount, with their active
     * subscription count. Computed with two grouped queries (paid totals and
     * active-subscription counts) joined in PHP — no per-workspace queries.
     *
     * @return array<int, array{workspace_id: string, name: string, paid_total: string, active_subscriptions: int}>
     */
    private function topWorkspaces(): array
    {
        $paidTotals = Invoice::query()
            ->join('subscriptions', 'invoices.subscription_id', '=', 'subscriptions.id')
            ->where('invoices.status', InvoiceStatus::Paid->value)
            ->selectRaw('subscriptions.workspace_id as workspace_id, SUM(invoices.amount) as paid_total')
            ->groupBy('subscriptions.workspace_id')
            ->orderByDesc('paid_total')
            ->limit(10)
            ->get();

        $workspaceIds = $paidTotals->pluck('workspace_id')->all();

        if ($workspaceIds === []) {
            return [];
        }

        $names = Workspace::query()
            ->whereIn('id', $workspaceIds)
            ->pluck('name', 'id');

        $activeCounts = Subscription::query()
            ->whereIn('workspace_id', $workspaceIds)
            ->where('status', SubscriptionStatus::Active->value)
            ->selectRaw('workspace_id, COUNT(*) as aggregate')
            ->groupBy('workspace_id')
            ->pluck('aggregate', 'workspace_id');

        return $paidTotals
            ->map(fn ($row): array => [
                'workspace_id' => (string) $row->workspace_id,
                'name' => (string) ($names->get($row->workspace_id) ?? ''),
                'paid_total' => $this->money($row->paid_total),
                'active_subscriptions' => (int) $activeCounts->get($row->workspace_id, 0),
            ])
            ->all();
    }

    /**
     * Workspace counts per status (zero-filled across every status).
     *
     * @return array<string, int>
     */
    private function workspacesByStatus(): array
    {
        $counts = $this->countsByColumn(Workspace::query()->getModel(), 'status');

        $result = [];
        foreach (WorkspaceStatus::cases() as $status) {
            $result[$status->value] = (int) $counts->get($status->value, 0);
        }

        return $result;
    }

    /**
     * User counts per role (zero-filled across every role).
     *
     * @return array<string, int>
     */
    private function usersByRole(): array
    {
        $counts = $this->countsByColumn(User::query()->getModel(), 'role');

        $result = [];
        foreach (UserRole::cases() as $role) {
            $result[$role->value] = (int) $counts->get($role->value, 0);
        }

        return $result;
    }

    /**
     * The trailing window of "%Y-%m" labels, oldest first (inclusive of the
     * current month), e.g. ["2025-07", ..., "2026-06"].
     *
     * @return Collection<int, string>
     */
    private function monthWindow(): Collection
    {
        $cursor = Carbon::now()->startOfMonth()->subMonths(self::MONTHS - 1);

        return Collection::times(
            self::MONTHS,
            function (int $offset) use ($cursor): string {
                return $cursor->copy()->addMonths($offset - 1)->format('Y-m');
            },
        );
    }

    /**
     * Single grouped `SELECT column, COUNT(*)` keyed by the column value.
     *
     * @return Collection<string, int>
     */
    private function countsByColumn(Model $model, string $column): Collection
    {
        /** @var Collection<string, int> $counts */
        $counts = $model->newQuery()
            ->selectRaw("{$column} as bucket, COUNT(*) as aggregate")
            ->groupBy($column)
            ->pluck('aggregate', 'bucket');

        return $counts;
    }

    /**
     * Normalise a (possibly null) decimal aggregate to a "0.00" string.
     */
    private function money(mixed $value): string
    {
        return number_format((float) ($value ?? 0), 2, '.', '');
    }
}
