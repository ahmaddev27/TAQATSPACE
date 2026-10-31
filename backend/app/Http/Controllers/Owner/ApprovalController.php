<?php

declare(strict_types=1);

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListApprovalsRequest;
use App\Http\Resources\ApprovalRequestResource;
use App\Models\ApprovalRequest;
use App\Repositories\Eloquent\ApprovalRequestRepository;
use App\Services\Approvals\ApprovalService;
use App\Services\Approvals\ApprovalStateException;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * An owner's view of the change requests their own workspace submitted, and the
 * ability to withdraw a pending one.
 */
class ApprovalController extends Controller
{
    public function __construct(
        private readonly ApprovalService $approvals,
        private readonly ApprovalRequestRepository $requests,
    ) {}

    /**
     * GET /api/workspace/approvals
     */
    public function index(ListApprovalsRequest $request): JsonResponse
    {
        $workspace = $request->user()->workspace;

        if ($workspace === null) {
            return ApiResponse::error(__('messages.no_workspace'), 403);
        }

        $paginator = $this->requests->paginate($workspace->id, $request->validated());

        return ApiResponse::success([
            'requests' => ApprovalRequestResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/workspace/approvals/{approvalRequest}/cancel
     */
    public function cancel(Request $request, ApprovalRequest $approvalRequest): JsonResponse
    {
        $workspace = $request->user()->workspace;

        // Another workspace's request is reported as missing, never as forbidden.
        if ($workspace === null || $approvalRequest->workspace_id !== $workspace->id) {
            return ApiResponse::error(__('messages.approval_not_found'), 404);
        }

        try {
            $cancelled = $this->approvals->cancel($approvalRequest, $request->user());
        } catch (ApprovalStateException $e) {
            return ApiResponse::error($e->getMessage(), 409);
        }

        return ApiResponse::success(new ApprovalRequestResource($cancelled->load(['workspace', 'requester'])));
    }
}
