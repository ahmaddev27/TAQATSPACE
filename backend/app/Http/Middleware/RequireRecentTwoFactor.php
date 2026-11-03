<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Auth\TwoFactorService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Step-up gate (alias `2fa.recent`) for sensitive actions such as issuing or
 * voiding invoices. Users with 2FA must have passed a code check within the
 * last 10 minutes; users without 2FA are not blocked (they cannot be asked).
 */
final class RequireRecentTwoFactor
{
    public const WINDOW_MINUTES = 10;

    public function __construct(
        private readonly TwoFactorService $twoFactor,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->hasTwoFactorEnabled()
            && ! $this->twoFactor->isRecentlyVerified($user, self::WINDOW_MINUTES)) {
            return response()->json([
                'message' => __('messages.two_factor_required'),
                'code' => 'two_factor_required',
            ], 403);
        }

        return $next($request);
    }
}
