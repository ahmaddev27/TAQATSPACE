<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Workspace\UpdateSeatTypesRequest;
use App\Http\Resources\WorkspaceResource;
use App\Services\SeatTypePricingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class SeatTypeController extends Controller
{
    public function __construct(
        private readonly SeatTypePricingService $pricing,
    ) {}

    /**
     * PUT /api/workspace/seat-types — owner only, authorized via gate.
     *
     * Upserts the per-seat-type pricing for the authenticated owner's workspace
     * and returns the refreshed workspace (including all seat_types rows).
     */
    public function update(UpdateSeatTypesRequest $request): JsonResponse
    {
        $workspace = $request->user()->workspace;

        if ($workspace === null) {
            return ApiResponse::error(__('messages.no_workspace_to_update'), 404);
        }

        if (Gate::denies('manage-workspace', $workspace)) {
            return ApiResponse::error(__('messages.unauthorized_action'), 403);
        }

        $this->pricing->sync($workspace, $request->validated('seat_types'));

        return ApiResponse::success(
            new WorkspaceResource($workspace->load('seatTypes')),
            __('messages.seat_type_pricing_updated'),
        );
    }
}
