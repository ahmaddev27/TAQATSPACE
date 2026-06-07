<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WorkspaceStatus;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Collection;

/**
 * Aggregates platform-wide counters for the super-admin dashboard.
 *
 * Every section is computed with a single grouped query (no per-row work and
 * no N+1), so the whole payload costs a handful of indexed aggregate queries.
 * "Revenue" here is admin-tracked figures, not gateway money: `paid` is the
 * sum of paid invoices, `outstanding` the sum of pending + overdue.
 */
class DashboardStatsService
{
    /**
     * @return array{
     *     workspaces: array{active: int, pending: int, suspended: int, total: int},
     *     users: array{freelancers: int, owners: int, admins: int, total: int, pending: int},
     *     subscriptions: array{active: int, total: int},
     *     invoices: array{paid: int, pending: int, overdue: int, total: int},
     *     revenue: array{paid: string, outstanding: string}
     * }
     */
    public function build(): array
    {
        $workspaceCounts = $this->countsByColumn(Workspace::query()->getModel(), 'status');
        $userRoleCounts = $this->countsByColumn(User::query()->getModel(), 'role');
        $userStatusCounts = $this->countsByColumn(User::query()->getModel(), 'status');
        $subscriptionCounts = $this->countsByColumn(Subscription::query()->getModel(), 'status');
        $invoiceCounts = $this->countsByColumn(Invoice::query()->getModel(), 'status');

        return [
            'workspaces' => [
                'active' => (int) $workspaceCounts->get(WorkspaceStatus::Active->value, 0),
                'pending' => (int) $workspaceCounts->get(WorkspaceStatus::Pending->value, 0),
                'suspended' => (int) $workspaceCounts->get(WorkspaceStatus::Suspended->value, 0),
                'total' => (int) $workspaceCounts->sum(),
            ],
            'users' => [
                'freelancers' => (int) $userRoleCounts->get(UserRole::Freelancer->value, 0),
                'owners' => (int) $userRoleCounts->get(UserRole::WorkspaceOwner->value, 0),
                'admins' => (int) $userRoleCounts->get(UserRole::Admin->value, 0),
                'total' => (int) $userRoleCounts->sum(),
                'pending' => (int) $userStatusCounts->get(UserStatus::PendingVerification->value, 0),
            ],
            'subscriptions' => [
                'active' => (int) $subscriptionCounts->get(SubscriptionStatus::Active->value, 0),
                'total' => (int) $subscriptionCounts->sum(),
            ],
            'invoices' => [
                'paid' => (int) $invoiceCounts->get(InvoiceStatus::Paid->value, 0),
                'pending' => (int) $invoiceCounts->get(InvoiceStatus::Pending->value, 0),
                'overdue' => (int) $invoiceCounts->get(InvoiceStatus::Overdue->value, 0),
                'total' => (int) $invoiceCounts->sum(),
            ],
            'revenue' => $this->revenue(),
        ];
    }

    /**
     * Tracked revenue figures: paid total and outstanding (pending + overdue).
     *
     * @return array{paid: string, outstanding: string}
     */
    private function revenue(): array
    {
        $paid = Invoice::query()
            ->where('status', InvoiceStatus::Paid->value)
            ->sum('amount');

        $outstanding = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Pending->value, InvoiceStatus::Overdue->value])
            ->sum('amount');

        return [
            'paid' => number_format((float) $paid, 2, '.', ''),
            'outstanding' => number_format((float) $outstanding, 2, '.', ''),
        ];
    }

    /**
     * Single grouped `SELECT column, COUNT(*)` keyed by the column value.
     *
     * @return Collection<string, int>
     */
    private function countsByColumn(\Illuminate\Database\Eloquent\Model $model, string $column): Collection
    {
        /** @var Collection<string, int> $counts */
        $counts = $model->newQuery()
            ->selectRaw("{$column} as bucket, COUNT(*) as aggregate")
            ->groupBy($column)
            ->pluck('aggregate', 'bucket');

        return $counts;
    }
}
