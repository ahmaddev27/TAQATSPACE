<?php

declare(strict_types=1);

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\UpdateWorkspaceMessagingRequest;
use App\Services\MessagingSettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Workspace-owner management of their own messaging accounts. An owner either
 * inherits Taqat's platform accounts (`use_platform = true`) or supplies their
 * own SMTP/SMS. Secrets are never returned (the {@see \App\Http\Resources\WorkspaceResource}
 * masks them). Behind auth:sanctum + role.owner; ownership re-checked via gate.
 */
class MessagingController extends Controller
{
    public function __construct(
        private readonly MessagingSettingsService $messaging,
    ) {}

    /**
     * PUT /api/workspace/messaging — set `use_platform` and/or the workspace's
     * own SMTP/SMS config. Secrets are encrypted; blank/omitted secrets
     * preserve the stored value.
     */
    public function update(UpdateWorkspaceMessagingRequest $request): JsonResponse
    {
        $workspace = $request->user()->workspace;

        if ($workspace === null) {
            return ApiResponse::error('You have no workspace to update.', 404);
        }

        if (Gate::denies('manage-workspace', $workspace)) {
            return ApiResponse::error('This action is unauthorized.', 403);
        }

        $saved = $this->messaging->updateWorkspace($workspace, $request->messagingData());

        return ApiResponse::success($saved, 'Workspace messaging settings updated.');
    }
}
