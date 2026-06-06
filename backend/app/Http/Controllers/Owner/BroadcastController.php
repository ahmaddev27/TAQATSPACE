<?php

declare(strict_types=1);

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\OwnerBroadcastRequest;
use App\Services\Messaging\BroadcastConfigException;
use App\Services\Messaging\BroadcastService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Workspace-owner compose-and-broadcast: send an email and/or SMS to the owner's
 * OWN members — all, a subscription-status segment, or specific members — through
 * the workspace's own accounts (or the platform when `use_platform`). Behind
 * auth:sanctum + role.owner; recipients are hard-scoped to the owner's workspace.
 */
class BroadcastController extends Controller
{
    public function __construct(
        private readonly BroadcastService $broadcasts,
    ) {}

    /**
     * POST /api/workspace/messaging/broadcast
     */
    public function store(OwnerBroadcastRequest $request): JsonResponse
    {
        $workspace = $request->user()->workspace;

        if ($workspace === null) {
            return ApiResponse::error(__('messages.no_workspace_to_update'), 404);
        }

        if (Gate::denies('manage-workspace', $workspace)) {
            return ApiResponse::error(__('messages.unauthorized_action'), 403);
        }

        try {
            $result = $this->broadcasts->broadcastFromWorkspace($workspace, $request->toData());
        } catch (BroadcastConfigException $e) {
            return ApiResponse::error(__($e->translationKey), 422);
        }

        return ApiResponse::success($result->toArray(), __('messages.broadcast_queued'));
    }
}
