<?php

declare(strict_types=1);

namespace App\Http\Controllers\Owner;

use App\Enums\StaffGroup;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\StoreStaffInvitationRequest;
use App\Http\Requests\Owner\UpdateStaffPermissionsRequest;
use App\Http\Resources\CashierInvitationResource;
use App\Http\Resources\StaffResource;
use App\Models\CashierInvitation;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Pos\StaffManagementService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Workspace staff administration for the authenticated owner's workspace
 * ONLY. Every action is scoped to that workspace.
 */
class StaffController extends Controller
{
    public function __construct(
        private readonly StaffManagementService $staff,
    ) {}

    /**
     * GET /api/workspace/staff
     *
     * The workspace's staff accounts plus any open (unaccepted) invitations.
     */
    public function index(Request $request): JsonResponse
    {
        $workspace = $this->ownerWorkspace($request);

        if ($workspace === null) {
            return ApiResponse::success(['staff' => [], 'cashiers' => [], 'invitations' => []]);
        }

        $members = StaffResource::collection(
            $workspace->cashiers()->with('permissions:id,name')->latest()->get()
        );
        $invitations = CashierInvitation::query()
            ->where('workspace_id', $workspace->id)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->get();

        return ApiResponse::success([
            'staff' => $members,
            // Deprecated alias of `staff` for pre-1.3 clients.
            'cashiers' => $members,
            'invitations' => CashierInvitationResource::collection($invitations),
        ]);
    }

    /**
     * POST /api/workspace/staff/invite
     */
    public function invite(StoreStaffInvitationRequest $request): JsonResponse
    {
        $workspace = $this->ownerWorkspace($request);

        if ($workspace === null) {
            return ApiResponse::error(__('messages.no_workspace'), 403);
        }

        try {
            $invitation = $this->staff->invite($workspace, $request->user(), $request->validated());
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(
            new CashierInvitationResource($invitation),
            __('messages.cashier_invited'),
            201,
        );
    }

    /**
     * POST /api/workspace/staff/invitations/{invitation}/resend
     */
    public function resendInvitation(Request $request, CashierInvitation $invitation): JsonResponse
    {
        $workspace = $this->ownerWorkspace($request);

        if ($workspace === null) {
            return ApiResponse::error(__('messages.no_workspace'), 403);
        }

        $refreshed = $this->staff->resendInvitation($workspace, $invitation);

        return ApiResponse::success(
            new CashierInvitationResource($refreshed),
            __('messages.cashier_invite_resent'),
        );
    }

    /**
     * DELETE /api/workspace/staff/invitations/{invitation}
     */
    public function destroyInvitation(Request $request, CashierInvitation $invitation): JsonResponse
    {
        $workspace = $this->ownerWorkspace($request);

        if ($workspace === null) {
            return ApiResponse::error(__('messages.no_workspace'), 403);
        }

        $this->staff->deleteInvitation($workspace, $invitation);

        return ApiResponse::success(null, __('messages.cashier_invite_deleted'));
    }

    /**
     * PUT /api/workspace/staff/{cashier}/permissions
     */
    public function updatePermissions(UpdateStaffPermissionsRequest $request, User $cashier): JsonResponse
    {
        $workspace = $this->ownerWorkspace($request);

        if ($workspace === null) {
            return ApiResponse::error(__('messages.no_workspace'), 403);
        }

        $updated = $this->staff->updatePermissions(
            $cashier,
            $workspace,
            $request->validated()['permissions'],
            StaffGroup::tryFrom((string) $request->validated('staff_group')),
        );

        return ApiResponse::success(new StaffResource($updated), __('messages.cashier_updated'));
    }

    /**
     * DELETE /api/workspace/staff/{cashier}
     */
    public function destroy(Request $request, User $cashier): JsonResponse
    {
        $workspace = $this->ownerWorkspace($request);

        if ($workspace === null) {
            return ApiResponse::error(__('messages.no_workspace'), 403);
        }

        $this->staff->deactivate($cashier, $workspace);

        return ApiResponse::success(null, __('messages.cashier_deactivated'));
    }

    /**
     * DELETE /api/workspace/staff/{cashier}/permanent
     */
    public function deleteStaff(Request $request, User $cashier): JsonResponse
    {
        $workspace = $this->ownerWorkspace($request);

        if ($workspace === null) {
            return ApiResponse::error(__('messages.no_workspace'), 403);
        }

        $this->staff->deleteStaff($cashier, $workspace);

        return ApiResponse::success(null, __('messages.cashier_deleted'));
    }

    private function ownerWorkspace(Request $request): ?Workspace
    {
        return $request->user()->workspace;
    }
}
