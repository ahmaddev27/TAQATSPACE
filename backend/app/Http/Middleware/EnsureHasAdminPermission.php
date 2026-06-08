<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Parameterized admin-permission gate. Requires an authenticated, active user
 * who holds the named Spatie permission, e.g. `can.permission:manage_billing`.
 * Pair with `auth:sanctum` + `role.admin` in the route group.
 *
 * A `super_admin` holds every permission; a standard `admin` holds the curated
 * default subset; a custom admin holds exactly what was granted — so a grant
 * actually restricts which admin areas the account can reach.
 */
final class EnsureHasAdminPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if ($user === null) {
            return ApiResponse::message(__('messages.unauthenticated'), 401);
        }

        if ($user->status !== UserStatus::Active) {
            $message = $user->status === UserStatus::Suspended
                ? __('messages.account_suspended')
                : __('messages.account_pending');

            return ApiResponse::message($message, 403);
        }

        if (! $user->can($permission)) {
            return ApiResponse::message(__('messages.insufficient_permissions'), 403);
        }

        return $next($request);
    }
}
