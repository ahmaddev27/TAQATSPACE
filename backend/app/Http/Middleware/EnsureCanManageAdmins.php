<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\AdminPermission;
use App\Enums\UserStatus;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the admin-management module. Requires an authenticated, active user
 * who holds the `manage_admins` permission (granted only to `super_admin`).
 * Pair with `auth:sanctum` in the route group.
 */
final class EnsureCanManageAdmins
{
    public function handle(Request $request, Closure $next): Response
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

        if (! $user->can(AdminPermission::ManageAdmins->value)) {
            return ApiResponse::message(__('messages.insufficient_permissions'), 403);
        }

        return $next($request);
    }
}
