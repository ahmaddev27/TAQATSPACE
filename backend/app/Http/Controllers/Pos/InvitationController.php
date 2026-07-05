<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\AcceptInvitationRequest;
use App\Models\CashierInvitation;
use App\Services\Pos\CashierManagementService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Public (token-authed) endpoints for a café/cashier staff member to view and
 * accept an emailed invitation. No session is required — the tokenised link IS
 * the credential.
 */
class InvitationController extends Controller
{
    public function __construct(
        private readonly CashierManagementService $cashiers,
    ) {}

    /**
     * GET /api/cashier/invitation?token=...
     *
     * Minimal preview so the accept screen can greet the invitee and show which
     * workspace invited them, without exposing anything sensitive.
     */
    public function show(Request $request): JsonResponse
    {
        $token = (string) $request->query('token', '');

        $invitation = CashierInvitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->with('workspace:id,name')
            ->first();

        if ($invitation === null || ! $invitation->isPending()) {
            return ApiResponse::error(__('messages.cashier_invite_invalid'), 404);
        }

        return ApiResponse::success([
            'email' => $invitation->email,
            'name' => $invitation->name,
            'workspace_name' => $invitation->workspace?->name,
        ]);
    }

    /**
     * POST /api/cashier/accept
     *
     * Provision the cashier account from the invitation and return an API token
     * for immediate sign-in.
     */
    public function accept(AcceptInvitationRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $result = $this->cashiers->accept($data['token'], [
                'name' => $data['name'],
                'password' => $data['password'],
            ]);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success([
            'token' => $result['token'],
            'user' => [
                'id' => $result['user']->id,
                'name' => $result['user']->name,
                'email' => $result['user']->email,
                'role' => $result['user']->role->value,
            ],
        ], __('messages.cashier_welcome'), 201);
    }
}
