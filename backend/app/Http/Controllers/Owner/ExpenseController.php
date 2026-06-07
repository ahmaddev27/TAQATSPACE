<?php

declare(strict_types=1);

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\ListExpensesRequest;
use App\Http\Requests\Owner\StoreExpenseRequest;
use App\Http\Requests\Owner\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Models\Workspace;
use App\Services\ExpenseService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Operating-expense management for the authenticated owner's workspace ONLY.
 * Every action is scoped to that workspace; expenses on another workspace are
 * never listed or mutated.
 */
class ExpenseController extends Controller
{
    public function __construct(
        private readonly ExpenseService $expenses,
    ) {}

    /**
     * GET /api/workspace/expenses
     *
     * Paginated, filterable expenses (category + spent-on range) plus a spend
     * summary (total, this month, by-category).
     */
    public function index(ListExpensesRequest $request): JsonResponse
    {
        $workspace = $this->ownerWorkspace($request);

        if ($workspace === null) {
            return ApiResponse::success([
                'expenses' => [],
                'summary' => $this->emptySummary(),
                'meta' => $this->emptyMeta(),
            ]);
        }

        $filters = $request->validated();
        $paginator = $this->expenses->paginateForWorkspace($workspace, $filters);

        return ApiResponse::success([
            'expenses' => ExpenseResource::collection($paginator->items()),
            'summary' => $this->expenses->summaryForWorkspace($workspace, $filters),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/workspace/expenses
     */
    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $workspace = $this->ownerWorkspace($request);

        if ($workspace === null) {
            return ApiResponse::error(__('messages.no_workspace'), 403);
        }

        $expense = $this->expenses->create($workspace, $request->validated());

        return ApiResponse::success(
            new ExpenseResource($expense),
            __('messages.expense_created'),
            201,
        );
    }

    /**
     * PUT /api/workspace/expenses/{expense}
     */
    public function update(UpdateExpenseRequest $request, Expense $expense): JsonResponse
    {
        $workspace = $this->ownerWorkspace($request);

        if ($workspace === null) {
            return ApiResponse::error(__('messages.no_workspace'), 403);
        }

        try {
            $updated = $this->expenses->update($workspace, $expense, $request->validated());
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 403);
        }

        return ApiResponse::success(
            new ExpenseResource($updated),
            __('messages.expense_updated'),
        );
    }

    /**
     * DELETE /api/workspace/expenses/{expense}
     */
    public function destroy(Request $request, Expense $expense): JsonResponse
    {
        $workspace = $this->ownerWorkspace($request);

        if ($workspace === null) {
            return ApiResponse::error(__('messages.no_workspace'), 403);
        }

        try {
            $this->expenses->delete($workspace, $expense);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 403);
        }

        return ApiResponse::success(null, __('messages.expense_deleted'));
    }

    private function ownerWorkspace(Request $request): ?Workspace
    {
        return $request->user()->workspace;
    }

    /**
     * @return array{total: string, this_month: string, by_category: array<string, string>}
     */
    private function emptySummary(): array
    {
        return ['total' => '0', 'this_month' => '0', 'by_category' => []];
    }

    /**
     * @return array<string, int>
     */
    private function emptyMeta(): array
    {
        return [
            'current_page' => 1,
            'per_page' => 15,
            'total' => 0,
            'last_page' => 1,
        ];
    }
}
