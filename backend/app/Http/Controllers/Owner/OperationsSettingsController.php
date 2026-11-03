<?php

declare(strict_types=1);

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\SubmitOperationsSettingsRequest;
use App\Http\Resources\ApprovalRequestResource;
use App\Services\Approvals\OperationsSettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class OperationsSettingsController extends Controller
{
    public function __construct(private readonly OperationsSettingsService $operations) {}

    /**
     * PUT /api/workspace/settings/operations — owner only.
     *
     * Capacity, seating mode, working hours, prices and tax never apply directly:
     * each changed group becomes an approval request (202). Nothing changed → 200.
     */
    public function update(SubmitOperationsSettingsRequest $request): JsonResponse
    {
        $workspace = $request->user()->workspace;

        if ($workspace === null) {
            return ApiResponse::error(__('messages.no_workspace_to_update'), 404);
        }

        if (Gate::denies('manage-workspace', $workspace)) {
            return ApiResponse::error(__('messages.unauthorized_action'), 403);
        }

        $requests = $this->operations->submit($workspace, $request->validated(), $request->user());

        if ($requests->isEmpty()) {
            return ApiResponse::success(['requests' => []], __('messages.operations_no_changes'));
        }

        return ApiResponse::success(
            ['requests' => ApprovalRequestResource::collection($requests)],
            __('messages.operations_submitted'),
            202,
        );
    }
}
