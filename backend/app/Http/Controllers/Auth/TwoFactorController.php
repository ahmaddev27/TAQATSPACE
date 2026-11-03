<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Exceptions\TwoFactorThrottledException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TwoFactorChallengeRequest;
use App\Http\Requests\Auth\TwoFactorCodeRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\AuthService;
use App\Services\Auth\TwoFactorService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * TOTP two-factor authentication endpoints. Eligibility (owner, staff, admin)
 * is enforced per action; the challenge is the only route a `2fa-pending`
 * token may reach (see RejectPendingTwoFactor).
 */
class TwoFactorController extends Controller
{
    public function __construct(
        private readonly TwoFactorService $twoFactor,
    ) {}

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return ApiResponse::success([
            'eligible' => $user->canUseTwoFactor(),
            'enabled' => $user->hasTwoFactorEnabled(),
            'pending_confirmation' => $user->two_factor_secret !== null && ! $user->hasTwoFactorEnabled(),
            'recovery_codes_remaining' => count($user->two_factor_recovery_codes ?? []),
        ]);
    }

    public function enable(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->canUseTwoFactor()) {
            return ApiResponse::error(__('messages.two_factor_not_eligible'), 403);
        }

        if ($user->hasTwoFactorEnabled()) {
            return ApiResponse::error(__('messages.two_factor_already_enabled'), 409);
        }

        return ApiResponse::success($this->twoFactor->enable($user));
    }

    public function confirm(TwoFactorCodeRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->canUseTwoFactor() || $user->two_factor_secret === null || $user->hasTwoFactorEnabled()) {
            return ApiResponse::error(__('messages.two_factor_no_pending_enrollment'), 409);
        }

        return $this->guarded(function () use ($user, $request): JsonResponse {
            $codes = $this->twoFactor->confirm($user, $request->validated('code'));

            return $codes === null
                ? $this->invalidCode()
                : ApiResponse::success(['recovery_codes' => $codes], __('messages.two_factor_enabled'));
        });
    }

    /** Complete the post-login challenge: upgrade the pending token to a full session. */
    public function challenge(TwoFactorChallengeRequest $request): JsonResponse
    {
        $user = $request->user();
        $token = $user->currentAccessToken();

        if (! $token instanceof PersonalAccessToken || ! in_array(AuthService::PENDING_ABILITY, $token->abilities ?? [], true)) {
            return ApiResponse::error(__('messages.two_factor_no_challenge'), 409);
        }

        return $this->guarded(function () use ($user, $token, $request): JsonResponse {
            if (! $this->verified($user, $request)) {
                return $this->invalidCode();
            }

            $token->forceFill(['abilities' => ['*']])->save();

            return ApiResponse::success([
                'user' => new UserResource($user),
                'role' => $user->role->value,
            ]);
        });
    }

    /** Step-up: re-verify a code within an authenticated session. */
    public function verify(TwoFactorChallengeRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            return ApiResponse::error(__('messages.two_factor_not_enabled'), 409);
        }

        return $this->guarded(fn (): JsonResponse => $this->verified($user, $request)
            ? ApiResponse::message(__('messages.two_factor_verified'))
            : $this->invalidCode());
    }

    public function disable(TwoFactorChallengeRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            return ApiResponse::error(__('messages.two_factor_not_enabled'), 409);
        }

        return $this->guarded(function () use ($user, $request): JsonResponse {
            if (! $this->verified($user, $request)) {
                return $this->invalidCode();
            }

            $this->twoFactor->disable($user);

            return ApiResponse::message(__('messages.two_factor_disabled'));
        });
    }

    private function verified(User $user, TwoFactorChallengeRequest $request): bool
    {
        return $this->twoFactor->verifyAny($user, $request->validated('code'), $request->validated('recovery_code'));
    }

    private function invalidCode(): JsonResponse
    {
        return ApiResponse::error(__('messages.two_factor_invalid_code'), 422, [
            'code' => [__('messages.two_factor_invalid_code')],
        ]);
    }

    /** Maps the throttle exception to HTTP 429 with a Retry-After header. */
    private function guarded(callable $action): JsonResponse
    {
        try {
            return $action();
        } catch (TwoFactorThrottledException $e) {
            return ApiResponse::error(__('messages.two_factor_throttled'), 429)
                ->header('Retry-After', (string) $e->retryAfterSeconds);
        }
    }
}
