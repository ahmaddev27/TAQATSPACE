<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\WorkspaceStatus;
use App\Http\Requests\Workspace\StoreWorkspaceRequest;
use App\Http\Requests\Workspace\UpdateWorkspaceRequest;
use App\Http\Resources\WorkspaceResource;
use App\Models\Workspace;
use App\Services\Admin\AdminWorkspaceService;
use App\Services\WorkspaceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use RuntimeException;

class WorkspaceController extends Controller
{
    public function __construct(
        private readonly WorkspaceService $workspaces,
        private readonly AdminWorkspaceService $adminWorkspaces,
    ) {}

    /**
     * GET /api/admin/workspaces/{workspace} — full detail for the admin: profile,
     * owner, seat types + occupancy, members, invoices + dues, and reviews.
     */
    public function adminShow(Workspace $workspace): JsonResponse
    {
        return ApiResponse::success($this->adminWorkspaces->detailFor($workspace));
    }

    /**
     * GET /api/workspaces — public, active-only, filtered & paginated.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'city', 'min_price', 'max_price', 'amenities', 'min_rating', 'search', 'sort',
        ]);

        $paginator = $this->workspaces->discover($filters);

        return ApiResponse::success(WorkspaceResource::collection($paginator)->response()->getData(true));
    }

    /**
     * GET /api/workspaces/cities — distinct active cities.
     */
    public function cities(): JsonResponse
    {
        return ApiResponse::success($this->workspaces->activeCities());
    }

    /**
     * GET /api/workspaces/{workspace} — public full detail.
     */
    public function show(string $workspace): JsonResponse
    {
        $model = $this->workspaces->findActivePublic($workspace);

        if ($model === null) {
            return ApiResponse::error(__('messages.workspace_not_found'), 404);
        }

        $model->setAttribute('seats_summary', $this->workspaces->seatsSummary($model->id));
        $model->setAttribute('recent_reviews', $this->workspaces->recentReviews($model->id));

        return ApiResponse::success(new WorkspaceResource($model));
    }

    /**
     * POST /api/workspace/create — owner only, one workspace per owner.
     */
    public function store(StoreWorkspaceRequest $request): JsonResponse
    {
        try {
            $workspace = $this->workspaces->createForOwner(
                (string) $request->user()->id,
                $request->validated(),
            );
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 409);
        }

        return ApiResponse::success(
            new WorkspaceResource($workspace),
            __('messages.workspace_created'),
            201,
        );
    }

    /**
     * PUT /api/workspace/settings — owner only, authorized via gate.
     */
    public function update(UpdateWorkspaceRequest $request): JsonResponse
    {
        $workspace = $request->user()->workspace;

        if ($workspace === null) {
            return ApiResponse::error(__('messages.no_workspace_to_update'), 404);
        }

        if (Gate::denies('manage-workspace', $workspace)) {
            return ApiResponse::error(__('messages.unauthorized_action'), 403);
        }

        $updated = $this->workspaces->updateSettings($workspace, $request->validated());

        return ApiResponse::success(new WorkspaceResource($updated), __('messages.workspace_updated'));
    }

    /**
     * GET /api/admin/workspaces — admin, all statuses, with owner info.
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'city', 'name']);

        $paginator = $this->workspaces->listForAdmin($filters);

        return ApiResponse::success(WorkspaceResource::collection($paginator)->response()->getData(true));
    }

    /**
     * PUT /api/admin/workspaces/{workspace}/status — admin status transition.
     */
    public function adminUpdateStatus(Request $request, Workspace $workspace): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([
                WorkspaceStatus::Active->value,
                WorkspaceStatus::Rejected->value,
                WorkspaceStatus::Suspended->value,
            ])],
            'reason' => ['required_if:status,'.WorkspaceStatus::Rejected->value
                .','.WorkspaceStatus::Suspended->value, 'nullable', 'string', 'max:1000'],
        ]);

        $updated = $this->workspaces->changeStatus(
            $workspace,
            WorkspaceStatus::from($validated['status']),
        );

        return ApiResponse::success(new WorkspaceResource($updated), __('messages.workspace_status_updated'));
    }

    /**
     * PUT /api/admin/workspaces/{workspace}/publish — admin publishes the
     * workspace to public discovery (separate from the account-status gate).
     */
    public function adminPublish(Workspace $workspace): JsonResponse
    {
        $updated = $this->workspaces->publish($workspace);

        return ApiResponse::success(new WorkspaceResource($updated), __('messages.workspace_published'));
    }

    /**
     * PUT /api/admin/workspaces/{workspace}/unpublish — admin removes the
     * workspace from public discovery without changing its account status.
     */
    public function adminUnpublish(Workspace $workspace): JsonResponse
    {
        $updated = $this->workspaces->unpublish($workspace);

        return ApiResponse::success(new WorkspaceResource($updated), __('messages.workspace_unpublished'));
    }
}
