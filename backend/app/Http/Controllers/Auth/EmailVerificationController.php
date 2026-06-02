<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    /**
     * Signed link from the verification email. Marks the email verified and,
     * for freelancers, activates the account, then redirects to the SPA.
     */
    public function verify(Request $request, string $id, string $hash): RedirectResponse|JsonResponse
    {
        $user = User::query()->findOrFail($id);

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return ApiResponse::error('Invalid verification link.', 403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();

            if ($user->role === UserRole::Freelancer) {
                $user->update(['status' => UserStatus::Active->value]);
            }

            event(new Verified($user));
        }

        $frontend = rtrim((string) config('app.frontend_url'), '/');

        return redirect()->away($frontend.'/login?verified=1');
    }

    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return ApiResponse::message('Email already verified.');
        }

        $user->sendEmailVerificationNotification();

        return ApiResponse::message('Verification link sent.');
    }
}
