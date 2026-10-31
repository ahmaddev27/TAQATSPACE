<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;

/**
 * Decides which workspace a request acts on: the one chosen by the router
 * (server-to-server requests), the one an owner owns, or the one a staff
 * account is bound to through `users.workspace_id`.
 */
final class CurrentWorkspaceResolver
{
    /** Request attribute the PUSH router sets once it has picked the target workspace. */
    public const ROUTED_ATTRIBUTE = 'routed_workspace';

    public function resolve(Request $request): ?Workspace
    {
        $routed = $request->attributes->get(self::ROUTED_ATTRIBUTE);

        if ($routed instanceof Workspace) {
            return $routed;
        }

        $user = $request->user();

        return $user instanceof User ? $this->resolveForUser($user) : null;
    }

    private function resolveForUser(User $user): ?Workspace
    {
        if ($user->role === UserRole::WorkspaceOwner) {
            return $user->workspace;
        }

        return $user->workspace_id !== null ? $user->employerWorkspace : null;
    }
}
