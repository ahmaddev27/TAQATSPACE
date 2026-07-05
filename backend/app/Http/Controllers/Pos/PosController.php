<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pos;

use App\Enums\PosPermission;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;

/**
 * Base for the operational POS endpoints, which are used by BOTH a workspace
 * owner and its cashiers. Centralises the two questions every POS action asks:
 * "which workspace am I operating?" and "may this actor do this?".
 */
abstract class PosController extends Controller
{
    /**
     * The single workspace the authenticated actor operates the POS for: the
     * owner's own workspace, or the workspace a cashier is bound to. Null for any
     * other account (they have no POS surface).
     */
    protected function posWorkspace(Request $request): ?Workspace
    {
        $user = $request->user();

        return $user->isCashier() ? $user->employerWorkspace : $user->workspace;
    }

    /**
     * Guard an action behind a POS permission. The owner (and platform admins)
     * always pass for their own workspace; a cashier must hold the specific
     * direct grant.
     */
    protected function requirePosPermission(User $user, PosPermission $permission): void
    {
        if ($user->isOwner() || $user->isAdmin()) {
            return;
        }

        if ($user->isCashier() && $user->can($permission->value)) {
            return;
        }

        abort(403, __('messages.insufficient_permissions'));
    }
}
