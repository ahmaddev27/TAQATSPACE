<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListApprovalsRequest;
use App\Http\Requests\Admin\RejectApprovalRequest;
use App\Http\Resources\ApprovalRequestResource;
use App\Models\ApprovalRequest;
use App\Repositories\Eloquent\ApprovalRequestRepository;
use App\Services\Approvals\ApprovalService;
use App\Services\Approvals\ApprovalStateException;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Platform-wide approval queue for admins holding `approve_requests`.
 */
class ApprovalController extends Controller
{
    public function __construct(
        private readonly ApprovalService $approvals,
        private readonly ApprovalRequestRepository $requests,
    ) {}

    /**
     * GET /api/admin/approvals
     */
    public function index(ListApprovalsRequest $request): JsonResponse
    {
        $paginator = $this->requests->paginate(null, $request->validated());

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
     * POST /api/admin/approvals/{approvalRequest}/approve
     */
    public function approve(Request $request, ApprovalRequest $approvalRequest): JsonResponse
    {
        try {
            $decided = $this->approvals->approve($approvalRequest, $request->user());
        } catch (ApprovalStateException $e) {
            return ApiResponse::error($e->getMessage(), 409);
        }

        return ApiResponse::success(new ApprovalRequestResource($decided->load(['workspace', 'requester'])));
    }

    /**
     * POST /api/admin/approvals/{approvalRequest}/reject
     */
    public function reject(RejectApprovalRequest $request, ApprovalRequest $approvalRequest): JsonResponse
    {
        try {
            $decided = $this->approvals->reject($approvalRequest, $request->user(), $request->validated('reason'));
        } catch (ApprovalStateException $e) {
            return ApiResponse::error($e->getMessage(), 409);
        }

        return ApiResponse::success(new ApprovalRequestResource($decided->load(['workspace', 'requester'])));
    }
}
