<?php

declare(strict_types=1);

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\InviteCashierRequest;
use App\Http\Requests\Owner\UpdateCashierPermissionsRequest;
use App\Http\Resources\CashierInvitationResource;
use App\Http\Resources\CashierResource;
use App\Models\CashierInvitation;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Pos\CashierManagementService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Café/cashier staff administration for the authenticated owner's workspace
 * ONLY. Every action is scoped to that workspace.
 */
class CashierController extends Controller
{
    public function __construct(
        private readonly CashierManagementService $cashiers,
    ) {}

    /**
     * GET /api/workspace/cashiers
     *
     * The workspace's cashier accounts plus any open (unaccepted) invitations.
     */
    public function index(Request $request): JsonResponse
    {
        $workspace = $this->ownerWorkspace($request);

        if ($workspace === null) {
            return ApiResponse::success(['cashiers' => [], 'invitations' => []]);
        }

        $cashiers = $workspace->cashiers()->with('permissions:id,name')->latest()->get();
        $invitations = CashierInvitation::query()
            ->where('workspace_id', $workspace->id)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->get();

        return ApiResponse::success([
            'cashiers' => CashierResource::collection($cashiers),
            'invitations' => CashierInvitationResource::collection($invitations),
        ]);
    }

    /**
     * POST /api/workspace/cashiers/invite
     */
    public function invite(InviteCashierRequest $request): JsonResponse
    {
        $workspace = $this->ownerWorkspace($request);

        if ($workspace === null) {
            return ApiResponse::error(__('messages.no_workspace'), 403);
        }

        try {
            $invitation = $this->cashiers->invite($workspace, $request->user(), $request->validated());
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
     * PUT /api/workspace/cashiers/{cashier}/permissions
     */
    public function updatePermissions(UpdateCashierPermissionsRequest $request, User $cashier): JsonResponse
    {
        $workspace = $this->ownerWorkspace($request);

        if ($workspace === null) {
            return ApiResponse::error(__('messages.no_workspace'), 403);
        }

        $updated = $this->cashiers->updatePermissions(
            $cashier,
            $workspace,
            $request->validated()['permissions'],
        );

        return ApiResponse::success(new CashierResource($updated), __('messages.cashier_updated'));
    }

    /**
     * DELETE /api/workspace/cashiers/{cashier}
     */
    public function destroy(Request $request, User $cashier): JsonResponse
    {
        $workspace = $this->ownerWorkspace($request);

        if ($workspace === null) {
            return ApiResponse::error(__('messages.no_workspace'), 403);
        }

        $this->cashiers->deactivate($cashier, $workspace);

        return ApiResponse::success(null, __('messages.cashier_deactivated'));
    }

    private function ownerWorkspace(Request $request): ?Workspace
    {
        return $request->user()->workspace;
    }
}
