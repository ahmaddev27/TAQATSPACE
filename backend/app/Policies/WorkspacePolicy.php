<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    /**
     * Admins may act on any workspace.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * Only the owner may update or manage their own workspace.
     */
    public function update(User $user, Workspace $workspace): bool
    {
        return $user->id === $workspace->owner_id;
    }

    public function managePhotos(User $user, Workspace $workspace): bool
    {
        return $user->id === $workspace->owner_id;
    }
}
