<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Auth\AuthService;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a challenge-only (`2fa-pending`) token from reaching anything except
 * the 2FA challenge and logout, so a password / SSO sign-in alone never yields
 * a usable session for an account protected by 2FA.
 */
final class RejectPendingTwoFactor
{
    /** @var list<string> */
    private const ALLOWED = ['api/auth/two-factor/challenge', 'api/auth/logout'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken() !== null && $this->isPending($request) && ! $request->is(self::ALLOWED)) {
            return response()->json([
                'message' => __('messages.two_factor_challenge_pending'),
                'code' => 'two_factor_pending',
            ], 403);
        }

        return $next($request);
    }

    private function isPending(Request $request): bool
    {
        $token = $request->user('sanctum')?->currentAccessToken();

        return $token instanceof PersonalAccessToken
            && in_array(AuthService::PENDING_ABILITY, $token->abilities ?? [], true);
    }
}
