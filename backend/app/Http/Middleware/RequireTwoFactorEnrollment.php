<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate (alias `2fa.enrolled`) for irreversible actions: `2fa.recent` lets users
 * without 2FA through, so these routes must also require that 2FA is enabled.
 */
final class RequireTwoFactorEnrollment
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->hasTwoFactorEnabled()) {
            return response()->json([
                'message' => __('messages.two_factor_enrollment_required'),
                'code' => 'two_factor_enrollment_required',
            ], 403);
        }

        return $next($request);
    }
}
