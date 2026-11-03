<?php

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ListCatalogRequest;
use App\Http\Requests\Finance\StoreCatalogItemRequest;
use App\Http\Requests\Finance\UpdateCatalogItemRequest;
use App\Http\Resources\CatalogItemResource;
use App\Repositories\Eloquent\CatalogRepository;
use App\Services\Finance\CatalogAccess;
use App\Services\Finance\CatalogItemService;
use App\Support\ApiResponse;
use App\Support\Tenancy\CurrentWorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Finance catalog items of the request's workspace. Routes sit behind the
 * `finance_v2` flag and the workspace context, so the workspace is always set.
 */
class CatalogItemController extends Controller
{
    public function __construct(
        private readonly CatalogItemService $items,
        private readonly CatalogRepository $repository,
        private readonly CatalogAccess $access,
        private readonly CurrentWorkspaceContext $context,
    ) {}

    /** GET /api/workspace/finance/catalog/items */
    public function index(ListCatalogRequest $request): JsonResponse
    {
        $paginator = $this->repository->paginateItems($this->context->workspace(), $request->validated());

        return ApiResponse::success([
            'items' => CatalogItemResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /** POST /api/workspace/finance/catalog/items */
    public function store(StoreCatalogItemRequest $request): JsonResponse
    {
        $item = $this->items->create($this->context->workspace(), $request->validated(), $request->user());

        return ApiResponse::success(new CatalogItemResource($item), __('messages.catalog_item_created'), 201);
    }

    /** PUT /api/workspace/finance/catalog/items/{id} */
    public function update(UpdateCatalogItemRequest $request, string $id): JsonResponse
    {
        $item = $this->repository->findItem($this->context->workspace(), $id);
        $updated = $this->items->update($item, $request->validated(), $request->user());

        return ApiResponse::success(new CatalogItemResource($updated), __('messages.catalog_item_updated'));
    }

    /** DELETE /api/workspace/finance/catalog/items/{id} (archives) */
    public function destroy(Request $request, string $id): JsonResponse
    {
        abort_unless($this->access->canManage($request->user()), 403, __('messages.insufficient_permissions'));

        $item = $this->repository->findItem($this->context->workspace(), $id);
        $archived = $this->items->archive($item, $request->user());

        return ApiResponse::success(new CatalogItemResource($archived), __('messages.catalog_item_archived'));
    }
}
