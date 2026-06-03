<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use App\Services\BookingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly BookingService $bookings,
    ) {}

    /**
     * Member's subscriptions — active plus full history, newest first.
     */
    public function index(Request $request): JsonResponse
    {
        $subscriptions = Subscription::query()
            ->where('member_id', $request->user()->id)
            ->with(['workspace:id,name,city,address,photos', 'seat:id,seat_number,type,status'])
            ->latest()
            ->get();

        return ApiResponse::success(SubscriptionResource::collection($subscriptions));
    }

    /**
     * Detail for one of the member's subscriptions, with seat, workspace and
     * inline invoices.
     */
    public function show(Request $request, Subscription $subscription): JsonResponse
    {
        $this->authorizeOwnership($request, $subscription);

        $subscription->load([
            'workspace:id,name,city,address,photos',
            'seat:id,seat_number,type,status',
            'invoices',
        ]);

        return ApiResponse::success(new SubscriptionResource($subscription));
    }

    /**
     * Member cancels their own subscription.
     */
    public function cancel(Request $request, Subscription $subscription): JsonResponse
    {
        $this->authorizeOwnership($request, $subscription);

        $subscription = $this->bookings->cancelSubscription($subscription);

        return ApiResponse::success(
            new SubscriptionResource($subscription),
            'Subscription cancelled.',
        );
    }

    /**
     * Ensure the subscription belongs to the authenticated member.
     */
    private function authorizeOwnership(Request $request, Subscription $subscription): void
    {
        if ($subscription->member_id !== $request->user()->id) {
            abort(403, 'You do not have access to this subscription.');
        }
    }
}
