<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\FinancePermission;
use App\Services\Finance\FinanceAccess;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds a finance route to a staff permission, e.g. `finance.permission:finance_collect`.
 * Several names mean "any of". Owners and admins always pass. Issue/void routes
 * chain `2fa.enrolled` then `2fa.recent` after it (`2fa.recent` alone lets
 * never-enrolled staff through), so the permission alone is never enough.
 */
final class EnsureFinancePermission
{
    public function __construct(private readonly FinanceAccess $access) {}

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if ($user === null) {
            return ApiResponse::message(__('messages.unauthenticated'), 401);
        }

        foreach ($permissions as $name) {
            $permission = FinancePermission::tryFrom($name)
                ?? throw new InvalidArgumentException("Unknown finance permission [{$name}].");

            if ($this->access->can($user, $permission)) {
                return $next($request);
            }
        }

        return ApiResponse::message(__('messages.insufficient_permissions'), 403);
    }
}
