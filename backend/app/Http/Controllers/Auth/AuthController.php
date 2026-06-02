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
            return ApiResponse::error('Invalid email or password.', 401);
        }

        if ($user->status === UserStatus::Suspended) {
            return ApiResponse::error('Account suspended.', 403);
        }

        if ($user->status === UserStatus::PendingVerification) {
            return ApiResponse::error('Account pending verification.', 403);
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
        $this->auth->logout($request->user());

        return response()->json(null, 204);
    }
}
