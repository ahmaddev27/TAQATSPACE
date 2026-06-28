<?php

declare(strict_types=1);

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\ListSubscriptionsRequest;
use App\Http\Resources\OwnerSubscriptionResource;
use App\Models\Subscription;
use App\Models\Workspace;
use App\Services\BookingService;
use App\Services\InternetAccessService;
use App\Services\OwnerSubscriptionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly OwnerSubscriptionService $subscriptions,
    ) {}

    /**
     * GET /api/workspace/subscriptions
     *
     * Paginated, filterable subscriptions for the authenticated owner's
     * workspace ONLY — used to track status, renew, and handle expiries.
     */
    public function index(ListSubscriptionsRequest $request): JsonResponse
    {
        $workspace = $this->ownerWorkspace($request);

        if ($workspace === null) {
            return ApiResponse::success(['subscriptions' => [], 'meta' => $this->emptyMeta()]);
        }

        $paginator = $this->subscriptions->paginateForWorkspace($workspace, $request->validated());

        return ApiResponse::success([
            'subscriptions' => OwnerSubscriptionResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * PUT /api/workspace/subscriptions/{subscription}/renew
     *
     * Extend the subscription by one plan cycle, reactivate it, and raise the
     * next pending invoice for the new period.
     */
    public function renew(Request $request, Subscription $subscription): JsonResponse
    {
        $workspace = $this->ownerWorkspace($request);

        if ($workspace === null) {
            return ApiResponse::error(__('messages.subscription_no_access'), 403);
        }

        try {
            $renewed = $this->subscriptions->renew($workspace, $subscription);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(new OwnerSubscriptionResource($renewed));
    }

    /**
     * PUT /api/workspace/subscriptions/{subscription}/cancel
     *
     * Cancel the subscription and free its seat (reuses the shared cancel path).
     */
    public function cancel(Request $request, Subscription $subscription, BookingService $bookings): JsonResponse
    {
        $workspace = $this->ownerWorkspace($request);

        if ($workspace === null) {
            return ApiResponse::error(__('messages.subscription_no_access'), 403);
        }

        try {
            $cancelled = $this->subscriptions->cancel($workspace, $subscription, $bookings);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(
            new OwnerSubscriptionResource($cancelled),
            __('messages.subscription_cancelled'),
        );
    }

    /**
     * POST /api/workspace/subscriptions/{subscription}/internet
     *
     * Issue (or regenerate) the member's internet credentials and send them by
     * email and SMS. Returns the credentials and which channels were delivered.
     */
    public function provisionInternet(
        Request $request,
        Subscription $subscription,
        InternetAccessService $internet,
    ): JsonResponse {
        $workspace = $this->ownerWorkspace($request);

        if ($workspace === null || $subscription->workspace_id !== $workspace->id) {
            return ApiResponse::error(__('messages.subscription_no_access'), 403);
        }

        $result = $internet->provision($subscription);

        return ApiResponse::success($result, __('messages.internet_credentials_sent'));
    }

    private function ownerWorkspace(Request $request): ?Workspace
    {
        return $request->user()->workspace;
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
