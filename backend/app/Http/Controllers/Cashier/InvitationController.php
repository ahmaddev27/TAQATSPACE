<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\CashierInvitation;
use App\Services\Pos\CashierManagementService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Authenticated endpoints for an SSO user to accept or decline a cashier
 * invitation addressed to their (IdP-verified) email during onboarding. The
 * emailed link is no longer a credential — the signed-in account is.
 */
class InvitationController extends Controller
{
    public function __construct(
        private readonly CashierManagementService $cashiers,
    ) {}

    /**
     * POST /api/cashier/invitations/{invitation}/accept
     *
     * Adopt the cashier role/workspace/permissions and complete onboarding.
     */
    public function accept(Request $request, CashierInvitation $invitation): JsonResponse
    {
        try {
            $user = $this->cashiers->acceptForUser($request->user(), $invitation);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success([
            'user' => new UserResource($user),
            'role' => $user->role->value,
        ], __('messages.cashier_welcome'));
    }

    /**
     * POST /api/cashier/invitations/{invitation}/decline
     *
     * Reject the invitation so the user proceeds to the normal role choice.
     */
    public function decline(Request $request, CashierInvitation $invitation): JsonResponse
    {
        $this->cashiers->declineForUser($request->user(), $invitation);

        return ApiResponse::success(null);
    }
}
