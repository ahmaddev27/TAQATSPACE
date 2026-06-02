<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base guard for role-gated routes. Requires an authenticated, active user
 * whose role matches. Pair with `auth:sanctum` in the route group.
 */
abstract class EnsureRole
{
    abstract protected function role(): UserRole;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return ApiResponse::message('Unauthenticated.', 401);
        }

        if (! $user->isActive()) {
            $message = $user->status === UserStatus::Suspended
                ? 'Account suspended.'
                : 'Account pending verification.';

            return ApiResponse::message($message, 403);
        }

        if ($user->role !== $this->role()) {
            return ApiResponse::message('Insufficient permissions.', 403);
        }

        return $next($request);
    }
}
