<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Workspace\LogoUploadRequest;
use App\Http\Resources\WorkspaceResource;
use App\Services\WorkspaceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Manages the owner's workspace logo (a dedicated image, separate from the
 * owner's personal avatar).
 */
class LogoController extends Controller
{
    public function __construct(
        private readonly WorkspaceService $workspaces,
    ) {}

    /**
     * POST /api/workspace/logo — set/replace the workspace logo.
     */
    public function store(LogoUploadRequest $request): JsonResponse
    {
        $workspace = $this->authorizedWorkspace($request);

        if ($workspace instanceof JsonResponse) {
            return $workspace;
        }

        $updated = $this->workspaces->setLogo($workspace, $request->file('logo'));

        return ApiResponse::success(new WorkspaceResource($updated), __('messages.logo_uploaded'), 201);
    }

    /**
     * DELETE /api/workspace/logo — remove the workspace logo.
     */
    public function destroy(Request $request): JsonResponse
    {
        $workspace = $this->authorizedWorkspace($request);

        if ($workspace instanceof JsonResponse) {
            return $workspace;
        }

        $updated = $this->workspaces->removeLogo($workspace);

        return ApiResponse::success(new WorkspaceResource($updated), __('messages.logo_removed'));
    }

    /**
     * Resolve the requester's workspace and confirm they may manage it.
     */
    private function authorizedWorkspace(Request $request): \App\Models\Workspace|JsonResponse
    {
        $workspace = $request->user()->workspace;

        if ($workspace === null) {
            return ApiResponse::error(__('messages.no_workspace'), 404);
        }

        if (Gate::denies('manage-workspace', $workspace)) {
            return ApiResponse::error(__('messages.unauthorized_action'), 403);
        }

        return $workspace;
    }
}
