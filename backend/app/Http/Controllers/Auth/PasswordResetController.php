<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\Auth\PasswordResetService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

class PasswordResetController extends Controller
{
    public function __construct(
        private readonly PasswordResetService $service,
    ) {}

    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $this->service->sendResetLink($request->validated()['email']);

        // Neutral response — never reveal whether the email exists.
        return ApiResponse::message(__('messages.reset_link_sent'));
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $status = $this->service->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
        );

        if ($status === Password::PasswordReset) {
            return ApiResponse::message(__('messages.password_reset_success'));
        }

        return ApiResponse::error(__('messages.invalid_reset_token'), 422);
    }
}
