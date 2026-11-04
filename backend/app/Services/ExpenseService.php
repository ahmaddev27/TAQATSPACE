<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ExpenseCategory;
use App\Enums\WorkspaceFeature;
use App\Models\CatalogCategory;
use App\Models\Expense;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Owner-facing operating-expense management for a single workspace: a
 * filterable, paginated ledger plus aggregate spend insights, and create/edit/
 * delete — all strictly scoped to the owner's own workspace.
 */
class ExpenseService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly FeatureFlagService $features,
    ) {}

    /**
     * Paginated expenses for THIS workspace, newest spend first.
     *
     * @param  array{category?: string|null, catalog_category_id?: string|null, include_voided?: bool|null, from?: string|null, to?: string|null, per_page?: int|null}  $filters
     * @return LengthAwarePaginator<int, Expense>
     */
    public function paginateForWorkspace(Workspace $workspace, array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 200));

        return $this->filteredQuery($workspace, $filters, (bool) ($filters['include_voided'] ?? false))
            ->with('catalogCategory:id,code,name_ar,name_en')
            ->orderByDesc('spent_on')
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** Trailing month window for the monthly-spend chart. */
    private const CHART_MONTHS = 12;

    /**
     * Aggregate spend insights for the owner's overview: the all-time total for
     * the current filter, the current calendar-month total, a per-category
     * breakdown, and a trailing-12-month spend series. Computed in SQL (no row
     * hydration) to stay efficient.
     *
     * @param  array{category?: string|null, from?: string|null, to?: string|null}  $filters
     * @return array{total: string, this_month: string, by_category: array<string, string>, by_month: array<int, array{month: string, total: string}>}
     */
    public function summaryForWorkspace(Workspace $workspace, array $filters): array
    {
        $total = (string) $this->filteredQuery($workspace, $filters)->sum('amount');

        $thisMonth = (string) Expense::query()
            ->notVoided()
            ->where('workspace_id', $workspace->id)
            ->whereBetween('spent_on', [
                Carbon::now()->startOfMonth()->toDateString(),
                Carbon::now()->endOfMonth()->toDateString(),
            ])
            ->sum('amount');

        $byCategory = $this->categoryBreakdown($workspace, $filters);
        $byMonth = $this->monthlyBreakdown($workspace, $filters);

        return [
            'total' => $total,
            'this_month' => $thisMonth,
            'by_category' => $byCategory,
            'by_month' => $byMonth,
        ];
    }

    /**
     * Workspace-scoped, filter-aware query for the CSV export. Reuses the SAME
     * filtered builder as the list/summary so the download matches the on-screen
     * view (DRY); ordered oldest spend first for a readable ledger export.
     *
     * @param  array{category?: string|null, from?: string|null, to?: string|null}  $filters
     * @return Builder<Expense>
     */
    public function exportQueryForWorkspace(Workspace $workspace, array $filters): Builder
    {
        return $this->filteredQuery($workspace, $filters)
            ->orderBy('spent_on')
            ->orderBy('created_at');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Workspace $workspace, array $data, ?User $actor = null): Expense
    {
        $expense = $workspace->expenses()->create($this->withLegacyCategory($data))->refresh();

        $this->audit->log('finance.expense.created', $expense, actor: $actor, changes: [
            'amount' => $expense->amount,
            'catalog_category_id' => $expense->catalog_category_id,
            'method' => $expense->method?->value,
        ], workspaceId: $workspace->id);

        return $expense;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Workspace $workspace, Expense $expense, array $data): Expense
    {
        $this->assertOwnedBy($workspace, $expense);
        $this->assertNotVoided($expense);

        $expense->update($this->withLegacyCategory($data));

        return $expense->refresh();
    }

    public function delete(Workspace $workspace, Expense $expense): void
    {
        $this->assertOwnedBy($workspace, $expense);

        $expense->delete();
    }

    /** With Finance v2 on, removing an expense voids it instead of deleting the row. */
    public function usesSoftVoid(Workspace $workspace): bool
    {
        return $this->features->isEnabled($workspace->id, WorkspaceFeature::FinanceV2);
    }

    public function void(Workspace $workspace, Expense $expense, string $reason, ?User $actor = null): Expense
    {
        $this->assertOwnedBy($workspace, $expense);
        $this->assertNotVoided($expense);

        $expense->forceFill(['voided_at' => now(), 'void_reason' => $reason])->save();
        $this->audit->log('finance.expense.voided', $expense, reason: $reason, actor: $actor, changes: [
            'amount' => $expense->amount,
        ], workspaceId: $workspace->id);

        return $expense->refresh();
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
     * Trailing-12-month spend series for the chart, filter-aware and
     * workspace-scoped. One grouped query (MySQL DATE_FORMAT month buckets, no
     * loop over rows); missing months are zero-filled in PHP so the series is
     * always contiguous and ascending.
     *
     * @param  array{category?: string|null, from?: string|null, to?: string|null}  $filters
     * @return array<int, array{month: string, total: string}>
     */
    private function monthlyBreakdown(Workspace $workspace, array $filters): array
    {
        $start = Carbon::now()->startOfMonth()->subMonthsNoOverflow(self::CHART_MONTHS - 1);

        $sums = $this->filteredQuery($workspace, $filters)
            ->where('spent_on', '>=', $start->toDateString())
            ->selectRaw("DATE_FORMAT(spent_on, '%Y-%m') as ym, SUM(amount) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $series = [];
        for ($i = 0; $i < self::CHART_MONTHS; $i++) {
            $key = $start->copy()->addMonthsNoOverflow($i)->format('Y-m');
            $series[] = [
                'month' => $key,
                'total' => (string) ($sums[$key] ?? '0'),
            ];
        }

        return $series;
    }

    /**
     * Base query scoped to the workspace with category + date-range filters.
     *
     * @param  array{category?: string|null, catalog_category_id?: string|null, from?: string|null, to?: string|null}  $filters
     * @return Builder<Expense>
     */
    private function filteredQuery(Workspace $workspace, array $filters, bool $includeVoided = false): Builder
    {
        $query = Expense::query()->where('workspace_id', $workspace->id);

        if (! $includeVoided) {
            $query->notVoided();
        }

        if (! empty($filters['catalog_category_id'])) {
            $query->where('catalog_category_id', $filters['catalog_category_id']);
        }

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

    /**
     * Expenses created from a catalog category alone still get a legacy
     * `category`, so the old list, summary and export keep working.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withLegacyCategory(array $data): array
    {
        if (isset($data['category']) || empty($data['catalog_category_id'])) {
            return $data;
        }

        $code = (string) CatalogCategory::query()->withoutWorkspaceScope()
            ->whereKey($data['catalog_category_id'])->value('code');
        $data['category'] = ExpenseCategory::tryFrom(strtolower(substr($code, 4)))?->value
            ?? ExpenseCategory::Other->value;

        return $data;
    }

    private function assertNotVoided(Expense $expense): void
    {
        if ($expense->isVoided()) {
            throw ValidationException::withMessages(['expense' => [__('messages.expense_already_voided')]]);
        }
    }

    private function assertOwnedBy(Workspace $workspace, Expense $expense): void
    {
        if ($expense->workspace_id !== $workspace->id) {
            throw new RuntimeException(__('messages.expense_no_access'));
        }
    }
}
