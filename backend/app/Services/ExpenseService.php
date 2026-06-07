<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ExpenseCategory;
use App\Models\Expense;
use App\Models\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Owner-facing operating-expense management for a single workspace: a
 * filterable, paginated ledger plus aggregate spend insights, and create/edit/
 * delete — all strictly scoped to the owner's own workspace.
 */
class ExpenseService
{
    /**
     * Paginated expenses for THIS workspace, newest spend first.
     *
     * @param  array{category?: string|null, from?: string|null, to?: string|null, per_page?: int|null}  $filters
     * @return LengthAwarePaginator<int, Expense>
     */
    public function paginateForWorkspace(Workspace $workspace, array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 200));

        return $this->filteredQuery($workspace, $filters)
            ->orderByDesc('spent_on')
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Aggregate spend insights for the owner's overview: the all-time total for
     * the current filter, the current calendar-month total, and a per-category
     * breakdown. Computed in SQL (no row hydration) to stay efficient.
     *
     * @param  array{category?: string|null, from?: string|null, to?: string|null}  $filters
     * @return array{total: string, this_month: string, by_category: array<string, string>}
     */
    public function summaryForWorkspace(Workspace $workspace, array $filters): array
    {
        $total = (string) $this->filteredQuery($workspace, $filters)->sum('amount');

        $thisMonth = (string) Expense::query()
            ->where('workspace_id', $workspace->id)
            ->whereBetween('spent_on', [
                Carbon::now()->startOfMonth()->toDateString(),
                Carbon::now()->endOfMonth()->toDateString(),
            ])
            ->sum('amount');

        $byCategory = $this->categoryBreakdown($workspace, $filters);

        return [
            'total' => $total,
            'this_month' => $thisMonth,
            'by_category' => $byCategory,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Workspace $workspace, array $data): Expense
    {
        return $workspace->expenses()->create($data)->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Workspace $workspace, Expense $expense, array $data): Expense
    {
        $this->assertOwnedBy($workspace, $expense);

        $expense->update($data);

        return $expense->refresh();
    }

    public function delete(Workspace $workspace, Expense $expense): void
    {
        $this->assertOwnedBy($workspace, $expense);

        $expense->delete();
    }

    /**
     * Per-category totals for the current filter, with every category present
     * (zero when there is no spend) so the UI can render a stable breakdown.
     *
     * @param  array{category?: string|null, from?: string|null, to?: string|null}  $filters
     * @return array<string, string>
     */
    private function categoryBreakdown(Workspace $workspace, array $filters): array
    {
        $sums = $this->filteredQuery($workspace, $filters)
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        $breakdown = [];
        foreach (ExpenseCategory::cases() as $category) {
            $breakdown[$category->value] = (string) ($sums[$category->value] ?? '0');
        }

        return $breakdown;
    }

    /**
     * Base query scoped to the workspace with category + date-range filters.
     *
     * @param  array{category?: string|null, from?: string|null, to?: string|null}  $filters
     * @return Builder<Expense>
     */
    private function filteredQuery(Workspace $workspace, array $filters): Builder
    {
        $query = Expense::query()->where('workspace_id', $workspace->id);

        $category = $filters['category'] ?? null;
        if ($category !== null && $category !== 'all' && ExpenseCategory::tryFrom($category) !== null) {
            $query->where('category', $category);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('spent_on', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('spent_on', '<=', $filters['to']);
        }

        return $query;
    }

    private function assertOwnedBy(Workspace $workspace, Expense $expense): void
    {
        if ($expense->workspace_id !== $workspace->id) {
            throw new RuntimeException(__('messages.expense_no_access'));
        }
    }
}
