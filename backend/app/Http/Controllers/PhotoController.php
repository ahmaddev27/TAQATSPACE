<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Workspace\PhotoUploadRequest;
use App\Http\Resources\WorkspaceResource;
use App\Services\WorkspaceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class PhotoController extends Controller
{
    public function __construct(
        private readonly WorkspaceService $workspaces,
    ) {}

    /**
     * POST /api/workspace/photos — append photos to the owner's workspace.
     */
    public function store(PhotoUploadRequest $request): JsonResponse
    {
        $workspace = $request->user()->workspace;

        if ($workspace === null) {
            return ApiResponse::error('You have no workspace.', 404);
        }

        if (Gate::denies('manage-workspace', $workspace)) {
            return ApiResponse::error('This action is unauthorized.', 403);
        }

        try {
            $updated = $this->workspaces->addPhotos($workspace, $request->file('photos'));
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(new WorkspaceResource($updated), 'Photos uploaded.', 201);
    }

    /**
     * DELETE /api/workspace/photos — remove one photo by its stored path.
     */
    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string'],
        ]);

        $workspace = $request->user()->workspace;

        if ($workspace === null) {
            return ApiResponse::error('You have no workspace.', 404);
        }

        if (Gate::denies('manage-workspace', $workspace)) {
            return ApiResponse::error('This action is unauthorized.', 403);
        }

        try {
            $updated = $this->workspaces->removePhoto($workspace, $validated['path']);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        }

        return ApiResponse::success(new WorkspaceResource($updated), 'Photo removed.');
    }
}
