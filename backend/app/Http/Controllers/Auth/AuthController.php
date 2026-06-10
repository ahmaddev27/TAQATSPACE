<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\AuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $this->auth->attempt($data['email'], $data['password']);

        if ($user === null) {
            return ApiResponse::error(__('messages.invalid_credentials'), 401);
        }

        if ($user->status === UserStatus::Suspended) {
            return ApiResponse::error(__('messages.account_suspended'), 403);
        }

        if ($user->status === UserStatus::PendingVerification) {
            return ApiResponse::error(__('messages.account_pending'), 403);
        }

        return ApiResponse::success([
            'user' => new UserResource($user),
            'token' => $this->auth->issueToken($user),
            'token_type' => 'Bearer',
            'role' => $user->role->value,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'user' => new UserResource($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        \App\Support\SsoLogoutLog::write('AuthController::logout ENTER', [
            'user_id' => $request->user()?->id,
            'authenticated' => $request->user() !== null,
        ]);

        $ssoLogoutUrl = $this->auth->logout($request->user());

        // RP-initiated single logout: when the session was opened via Taqat SSO,
        // hand the frontend the IdP end-session URL so it can also sign the user
        // out upstream. Null for password sessions — the SPA then logs out locally.
        $payload = ['sso_logout_url' => $ssoLogoutUrl];

        \App\Support\SsoLogoutLog::write('AuthController::logout EXIT', [
            'sso_logout_url' => $ssoLogoutUrl,
            'response_payload' => $payload,
        ]);

        return ApiResponse::success($payload);
    }
}
