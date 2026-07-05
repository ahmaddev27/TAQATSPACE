<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pos;

use App\Enums\PosPermission;
use App\Enums\StockMovementType;
use App\Http\Requests\Pos\AdjustStockRequest;
use App\Http\Requests\Pos\StorePosProductRequest;
use App\Http\Requests\Pos\UpdatePosProductRequest;
use App\Http\Resources\PosProductResource;
use App\Models\PosProduct;
use App\Services\Pos\PosProductService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * POS catalogue + inventory endpoints, scoped to the actor's workspace (owner
 * or cashier). Listing is open to any POS actor; mutations require the
 * `pos_manage_products` permission (owners always pass).
 */
class ProductController extends PosController
{
    public function __construct(
        private readonly PosProductService $products,
    ) {}

    /**
     * GET /api/pos/products
     */
    public function index(Request $request): JsonResponse
    {
        $workspace = $this->posWorkspace($request);

        if ($workspace === null) {
            return ApiResponse::error(__('messages.no_workspace'), 403);
        }

        $paginator = $this->products->paginate($workspace, $request->only([
            'is_active', 'category', 'search', 'per_page',
        ]));

        return ApiResponse::success([
            'products' => PosProductResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/pos/products
     */
    public function store(StorePosProductRequest $request): JsonResponse
    {
        $workspace = $this->posWorkspace($request);

        if ($workspace === null) {
            return ApiResponse::error(__('messages.no_workspace'), 403);
        }

        $this->requirePosPermission($request->user(), PosPermission::ManageProducts);

        $product = $this->products->create($workspace, $request->validated(), $request->user());

        return ApiResponse::success(new PosProductResource($product), __('messages.pos_product_created'), 201);
    }

    /**
     * PUT /api/pos/products/{product}
     */
    public function update(UpdatePosProductRequest $request, PosProduct $product): JsonResponse
    {
        $workspace = $this->posWorkspace($request);

        if ($workspace === null) {
            return ApiResponse::error(__('messages.no_workspace'), 403);
        }

        $this->requirePosPermission($request->user(), PosPermission::ManageProducts);

        $updated = $this->products->update($workspace, $product, $request->validated());

        return ApiResponse::success(new PosProductResource($updated), __('messages.pos_product_updated'));
    }

    /**
     * DELETE /api/pos/products/{product}
     */
    public function destroy(Request $request, PosProduct $product): JsonResponse
    {
        $workspace = $this->posWorkspace($request);

        if ($workspace === null) {
            return ApiResponse::error(__('messages.no_workspace'), 403);
        }

        $this->requirePosPermission($request->user(), PosPermission::ManageProducts);

        $this->products->delete($workspace, $product);

        return ApiResponse::success(null, __('messages.pos_product_deleted'));
    }

    /**
     * POST /api/pos/products/{product}/stock
     *
     * Restock or manually adjust a tracked product's inventory.
     */
    public function adjustStock(AdjustStockRequest $request, PosProduct $product): JsonResponse
    {
        $workspace = $this->posWorkspace($request);

        if ($workspace === null) {
            return ApiResponse::error(__('messages.no_workspace'), 403);
        }

        $this->requirePosPermission($request->user(), PosPermission::ManageProducts);

        $data = $request->validated();

        try {
            $updated = $this->products->adjustStock(
                $workspace,
                $product,
                StockMovementType::from($data['type']),
                (int) $data['qty_change'],
                $data['note'] ?? null,
                $request->user(),
            );
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(new PosProductResource($updated), __('messages.pos_stock_updated'));
    }
}
