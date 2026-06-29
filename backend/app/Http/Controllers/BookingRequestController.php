<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Booking\IndexBookingRequest;
use App\Http\Requests\Booking\ReviewBookingRequest;
use App\Http\Requests\Booking\StoreBookingRequest;
use App\Http\Resources\BookingRequestResource;
use App\Models\BookingRequest;
use App\Services\BookingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BookingRequestController extends Controller
{
    public function __construct(
        private readonly BookingService $bookings,
    ) {}

    /**
     * Freelancer submits a booking request.
     */
    public function store(StoreBookingRequest $request): JsonResponse
    {
        $booking = $this->bookings->submit($request->user(), $request->validated());

        return ApiResponse::success(
            new BookingRequestResource($booking),
            __('messages.booking_submitted'),
            201,
        );
    }

    /**
     * Owner's paginated booking-request queue for their workspace.
     */
    public function index(IndexBookingRequest $request): JsonResponse
    {
        $workspace = $request->user()?->workspace;

        if ($workspace === null) {
            abort(404, __('messages.no_workspace_found_account'));
        }

        $bookings = $workspace->bookingRequests()
            ->with('member:id,name,email,specialty,avatar')
            ->when(
                $request->validated('status'),
                fn ($query, $status) => $query->where('status', $status),
            )
            ->latest()
            ->paginate((int) ($request->validated('per_page') ?? 15));

        return ApiResponse::success(BookingRequestResource::collection($bookings)->response()->getData(true));
    }

    /**
     * Owner approves or rejects a booking request.
     */
    public function update(ReviewBookingRequest $request, BookingRequest $bookingRequest): JsonResponse
    {
        Gate::forUser($request->user())->authorize('manage-workspace', $bookingRequest->workspace);

        $data = $request->validated();

        $booking = $data['action'] === 'approve'
            ? $this->bookings->approve($bookingRequest, $request->user(), $data['seat_id'] ?? null, $data['package_id'])
            : $this->bookings->reject($bookingRequest, $request->user(), $data['rejection_reason'] ?? null);

        return ApiResponse::success(
            new BookingRequestResource($booking),
            $data['action'] === 'approve' ? __('messages.booking_approved') : __('messages.booking_rejected'),
        );
    }
}
