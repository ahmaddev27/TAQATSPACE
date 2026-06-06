<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\WorkspaceStatus;
use App\Http\Requests\Announcement\StoreAnnouncementRequest;
use App\Http\Requests\Announcement\UpdateAnnouncementRequest;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use App\Models\Workspace;
use App\Services\AnnouncementService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function __construct(
        private readonly AnnouncementService $announcements,
    ) {}

    /**
     * GET /api/workspace/announcements
     *
     * Owner-only: every announcement of the owner's workspace, including
     * drafts and expired ones.
     */
    public function index(Request $request): JsonResponse
    {
        $workspace = $this->ownerWorkspace($request);

        if ($workspace === null) {
            return ApiResponse::success(['announcements' => []]);
        }

        return ApiResponse::success([
            'announcements' => AnnouncementResource::collection(
                $this->announcements->listForOwner($workspace),
            ),
        ]);
    }

    /**
     * POST /api/workspace/announcements
     *
     * Owner-only: create an announcement for the owner's workspace. If it is
     * published (now or earlier), active members are notified.
     */
    public function store(StoreAnnouncementRequest $request): JsonResponse
    {
        $workspace = $this->ownerWorkspace($request);

        if ($workspace === null) {
            return ApiResponse::error(__('messages.no_workspace_to_publish'), 404);
        }

        $announcement = $this->announcements->create(
            $workspace,
            $request->user(),
            $request->validated(),
        );

        return ApiResponse::success(
            new AnnouncementResource($announcement),
            __('messages.announcement_created'),
            201,
        );
    }

    /**
     * PUT /api/workspace/announcements/{announcement}
     *
     * Owner-only: update an announcement of the owner's workspace.
     */
    public function update(UpdateAnnouncementRequest $request, Announcement $announcement): JsonResponse
    {
        $workspace = $this->ownerWorkspace($request);

        if ($workspace === null) {
            return ApiResponse::error(__('messages.announcement_not_found'), 404);
        }

        $updated = $this->announcements->update(
            $workspace,
            $announcement,
            $request->validated(),
        );

        if ($updated === null) {
            return ApiResponse::error(__('messages.announcement_not_found'), 404);
        }

        return ApiResponse::success(
            new AnnouncementResource($updated),
            __('messages.announcement_updated'),
        );
    }

    /**
     * DELETE /api/workspace/announcements/{announcement}
     *
     * Owner-only: delete an announcement of the owner's workspace.
     */
    public function destroy(Request $request, Announcement $announcement): JsonResponse
    {
        $workspace = $this->ownerWorkspace($request);

        if ($workspace === null) {
            return ApiResponse::error(__('messages.announcement_not_found'), 404);
        }

        if (! $this->announcements->delete($workspace, $announcement)) {
            return ApiResponse::error(__('messages.announcement_not_found'), 404);
        }

        return ApiResponse::message(__('messages.announcement_deleted'));
    }

    /**
     * GET /api/workspaces/{workspace}/announcements
     *
     * Public: currently live (published, non-expired) announcements for an
     * active workspace only.
     */
    public function publicIndex(Workspace $workspace): JsonResponse
    {
        if ($workspace->status !== WorkspaceStatus::Active) {
            return ApiResponse::error(__('messages.workspace_not_found'), 404);
        }

        return ApiResponse::success([
            'announcements' => AnnouncementResource::collection(
                $this->announcements->listPublic($workspace),
            ),
        ]);
    }

    private function ownerWorkspace(Request $request): ?Workspace
    {
        return $request->user()->workspace;
    }
}
