<?php

declare(strict_types=1);

namespace App\Http\Controllers\Freelancer;

use App\Enums\PosOrderSource;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Freelancer\PlaceOrderRequest;
use App\Http\Resources\PosOrderResource;
use App\Http\Resources\PosProductResource;
use App\Models\PosOrder;
use App\Models\Subscription;
use App\Models\Workspace;
use App\Services\Pos\PosOrderService;
use App\Services\Pos\PosProductService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Lets a freelancer order from the café of a workspace they are actively
 * subscribed to. Orders are placed PENDING (source = freelancer) and land in the
 * cashier's queue to be settled at the counter — the freelancer never handles
 * payment here.
 */
class PosController extends Controller
{
    public function __construct(
        private readonly PosOrderService $orders,
        private readonly PosProductService $products,
    ) {}

    /**
     * GET /api/freelancer/pos/products?workspace_id=...
     *
     * The active café menu of a workspace the freelancer is subscribed to.
     */
    public function products(Request $request): JsonResponse
    {
        $workspace = $this->subscribedWorkspace($request->user()->id, (string) $request->query('workspace_id', ''));

        if ($workspace === null) {
            return ApiResponse::error(__('messages.pos_not_subscribed'), 403);
        }

        $paginator = $this->products->paginate($workspace, ['is_active' => true, 'per_page' => 100]);

        return ApiResponse::success(['products' => PosProductResource::collection($paginator->items())]);
    }

    /**
     * GET /api/freelancer/pos/orders — the freelancer's own café orders.
     */
    public function index(Request $request): JsonResponse
    {
        $orders = PosOrder::query()
            ->where('member_id', $request->user()->id)
            ->with('items')
            ->latest()
            ->limit(50)
            ->get();

        return ApiResponse::success(['orders' => PosOrderResource::collection($orders)]);
    }

    /**
     * POST /api/freelancer/pos/orders
     */
    public function store(PlaceOrderRequest $request): JsonResponse
    {
        $data = $request->validated();
        $workspace = $this->subscribedWorkspace($request->user()->id, $data['workspace_id']);

        if ($workspace === null) {
            return ApiResponse::error(__('messages.pos_not_subscribed'), 403);
        }

        try {
            $order = $this->orders->create($workspace, PosOrderSource::Freelancer, $data, null, $request->user());
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(new PosOrderResource($order), __('messages.pos_order_created'), 201);
    }

    /**
     * The workspace the freelancer may order from: one they hold an ACTIVE
     * subscription to. Null when there is no such subscription.
     */
    private function subscribedWorkspace(string $memberId, string $workspaceId): ?Workspace
    {
        if ($workspaceId === '') {
            return null;
        }

        $subscribed = Subscription::query()
            ->where('member_id', $memberId)
            ->where('workspace_id', $workspaceId)
            ->where('status', SubscriptionStatus::Active->value)
            ->exists();

        return $subscribed ? Workspace::query()->find($workspaceId) : null;
    }
}
