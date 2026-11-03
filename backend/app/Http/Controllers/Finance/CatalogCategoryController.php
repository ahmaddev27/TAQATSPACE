<?php

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ListCatalogRequest;
use App\Http\Requests\Finance\ReorderCatalogCategoriesRequest;
use App\Http\Requests\Finance\StoreCatalogCategoryRequest;
use App\Http\Requests\Finance\UpdateCatalogCategoryRequest;
use App\Http\Resources\CatalogCategoryResource;
use App\Repositories\Eloquent\CatalogRepository;
use App\Services\Finance\CatalogAccess;
use App\Services\Finance\CatalogCategoryService;
use App\Support\ApiResponse;
use App\Support\Tenancy\CurrentWorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Finance catalog categories of the request's workspace. Routes sit behind the
 * `finance_v2` flag and the workspace context, so the workspace is always set.
 */
class CatalogCategoryController extends Controller
{
    public function __construct(
        private readonly CatalogCategoryService $categories,
        private readonly CatalogRepository $repository,
        private readonly CatalogAccess $access,
        private readonly CurrentWorkspaceContext $context,
    ) {}

    /** GET /api/workspace/finance/catalog/categories */
    public function index(ListCatalogRequest $request): JsonResponse
    {
        $paginator = $this->repository->paginateCategories($this->context->workspace(), $request->validated());

        return ApiResponse::success([
            'categories' => CatalogCategoryResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /** POST /api/workspace/finance/catalog/categories */
    public function store(StoreCatalogCategoryRequest $request): JsonResponse
    {
        $category = $this->categories->create($this->context->workspace(), $request->validated(), $request->user());

        return ApiResponse::success(new CatalogCategoryResource($category), __('messages.catalog_category_created'), 201);
    }

    /** PUT /api/workspace/finance/catalog/categories/{id} */
    public function update(UpdateCatalogCategoryRequest $request, string $id): JsonResponse
    {
        $category = $this->repository->findCategory($this->context->workspace(), $id);
        $updated = $this->categories->update($category, $request->validated(), $request->user());

        return ApiResponse::success(new CatalogCategoryResource($updated), __('messages.catalog_category_updated'));
    }

    /** DELETE /api/workspace/finance/catalog/categories/{id} (archives) */
    public function destroy(Request $request, string $id): JsonResponse
    {
        abort_unless($this->access->canManage($request->user()), 403, __('messages.insufficient_permissions'));

        $category = $this->repository->findCategory($this->context->workspace(), $id);
        $archived = $this->categories->archive($category, $request->user());

        return ApiResponse::success(new CatalogCategoryResource($archived), __('messages.catalog_category_archived'));
    }

    /** POST /api/workspace/finance/catalog/categories/reorder */
    public function reorder(ReorderCatalogCategoriesRequest $request): JsonResponse
    {
        try {
            $this->categories->reorder($this->context->workspace(), $request->validated('ids'), $request->user());
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(null, __('messages.catalog_categories_reordered'));
    }
}
