<?php

declare(strict_types=1);

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\ResetWorkspaceRequest;
use App\Services\Workspace\Reset\WorkspaceResetService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class WorkspaceResetController extends Controller
{
    public function __construct(private readonly WorkspaceResetService $resets) {}

    /**
     * DELETE /api/workspace/danger/reset — owner only, behind recent 2FA.
     */
    public function destroy(ResetWorkspaceRequest $request): JsonResponse
    {
        $workspace = $request->user()->workspace;

        if ($workspace === null) {
            return ApiResponse::error(__('messages.no_workspace'), 403);
        }

        if (Gate::denies('manage-workspace', $workspace)) {
            return ApiResponse::error(__('messages.unauthorized_action'), 403);
        }

        $this->resets->reset($workspace, $request->user(), $request->validated('reason'));

        return ApiResponse::success(null, __('messages.workspace_reset_done'));
    }
}
