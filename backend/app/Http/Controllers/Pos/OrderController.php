<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pos;

use App\Enums\PaymentMethod;
use App\Enums\PosOrderSource;
use App\Enums\PosOrderStatus;
use App\Enums\PosPermission;
use App\Http\Requests\Pos\PayOrderRequest;
use App\Http\Requests\Pos\StoreOrderRequest;
use App\Http\Requests\Pos\UpdateOrderStatusRequest;
use App\Http\Resources\PosOrderResource;
use App\Models\PosOrder;
use App\Services\Pos\PosOrderService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Counter-side POS orders, operated by a cashier (or the owner). Most mutations
 * require the `pos_sell` permission; refunds require `pos_refund` (owners always
 * pass). An order carries two independent axes — fulfillment status and payment
 * — settled respectively via {@see setStatus}/{@see pay}. Stock is consumed at
 * payment and restored on {@see refund}.
 */
class OrderController extends PosController
{
    public function __construct(
        private readonly PosOrderService $orders,
    ) {}

    /**
     * GET /api/pos/orders
     */
    public function index(Request $request): JsonResponse
    {
        $workspace = $this->posWorkspace($request);

        if ($workspace === null) {
            return ApiResponse::error(__('messages.no_workspace'), 403);
        }

        $paginator = $this->orders->paginate($workspace, $request->only(['status', 'per_page']));

        return ApiResponse::success([
            'orders' => PosOrderResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/pos/summary — at-a-glance figures for the POS dashboard section.
     */
    public function summary(Request $request): JsonResponse
    {
        $workspace = $this->posWorkspace($request);

        if ($workspace === null) {
            return ApiResponse::error(__('messages.no_workspace'), 403);
        }

        $this->requirePosPermission($request->user(), PosPermission::ViewReports);

        return ApiResponse::success($this->orders->summary($workspace));
    }

    /**
     * GET /api/pos/orders/{order}
     */
    public function show(Request $request, PosOrder $order): JsonResponse
    {
        $workspace = $this->posWorkspace($request);

        if ($workspace === null || (string) $order->workspace_id !== (string) $workspace->id) {
            return ApiResponse::error(__('messages.pos_order_not_found'), 404);
        }

        return ApiResponse::success(new PosOrderResource($order->load(['items', 'payments', 'member:id,name'])));
    }

    /**
     * POST /api/pos/orders — ring up a walk-in / verbal order.
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $workspace = $this->posWorkspace($request);

        if ($workspace === null) {
            return ApiResponse::error(__('messages.no_workspace'), 403);
        }

        $this->requirePosPermission($request->user(), PosPermission::Sell);

        try {
            $order = $this->orders->create(
                $workspace,
                PosOrderSource::Cashier,
                $request->validated(),
                $request->user(),
                null,
            );
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(new PosOrderResource($order), __('messages.pos_order_created'), 201);
    }

    /**
     * POST /api/pos/orders/{order}/pay
     */
    public function pay(PayOrderRequest $request, PosOrder $order): JsonResponse
    {
        $workspace = $this->posWorkspace($request);

        if ($workspace === null) {
            return ApiResponse::error(__('messages.no_workspace'), 403);
        }

        $this->requirePosPermission($request->user(), PosPermission::Sell);

        try {
            $paid = $this->orders->pay(
                $workspace,
                $order,
                PaymentMethod::from($request->validated()['method']),
                $request->user(),
            );
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(new PosOrderResource($paid), __('messages.pos_order_paid'));
    }

    /**
     * POST /api/pos/orders/{order}/status — advance fulfillment status.
     */
    public function setStatus(UpdateOrderStatusRequest $request, PosOrder $order): JsonResponse
    {
        $workspace = $this->posWorkspace($request);

        if ($workspace === null) {
            return ApiResponse::error(__('messages.no_workspace'), 403);
        }

        $this->requirePosPermission($request->user(), PosPermission::Sell);

        try {
            $updated = $this->orders->setStatus(
                $workspace,
                $order,
                PosOrderStatus::from($request->validated()['status']),
                $request->user(),
            );
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(new PosOrderResource($updated), __('messages.pos_order_status_updated'));
    }

    /**
     * POST /api/pos/orders/{order}/refund — reverse a paid order.
     */
    public function refund(Request $request, PosOrder $order): JsonResponse
    {
        $workspace = $this->posWorkspace($request);

        if ($workspace === null) {
            return ApiResponse::error(__('messages.no_workspace'), 403);
        }

        $this->requirePosPermission($request->user(), PosPermission::Refund);

        try {
            $refunded = $this->orders->refund($workspace, $order, $request->user());
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(new PosOrderResource($refunded), __('messages.pos_order_refunded'));
    }

    /**
     * POST /api/pos/orders/{order}/cancel
     */
    public function cancel(Request $request, PosOrder $order): JsonResponse
    {
        $workspace = $this->posWorkspace($request);

        if ($workspace === null) {
            return ApiResponse::error(__('messages.no_workspace'), 403);
        }

        $this->requirePosPermission($request->user(), PosPermission::Sell);

        try {
            $cancelled = $this->orders->cancel($workspace, $order);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(new PosOrderResource($cancelled), __('messages.pos_order_cancelled'));
    }
}
