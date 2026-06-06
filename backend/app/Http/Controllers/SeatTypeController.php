<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Workspace\UpdateSeatTypesRequest;
use App\Http\Resources\WorkspaceResource;
use App\Services\WorkspaceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class SeatTypeController extends Controller
{
    public function __construct(
        private readonly WorkspaceService $workspaces,
    ) {}

    /**
     * PUT /api/workspace/seat-types — owner only, authorized via gate.
     *
     * Upserts the per-seat-type pricing for the authenticated owner's workspace,
     * recomputes the derived discovery columns, and returns the refreshed
     * workspace (including all seat_types rows).
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

        $updated = $this->workspaces->updateSeatTypes($workspace, $request->validated('seat_types'));

        return ApiResponse::success(
            new WorkspaceResource($updated->load('seatTypes')),
            __('messages.seat_type_pricing_updated'),
        );
    }
}
