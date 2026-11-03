<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\User;

/**
 * Who may touch workspace plans. Owners (and platform admins) manage them;
 * cashiers may read them to sell subscriptions.
 */
final class PlanAccess
{
    public function canView(User $user): bool
    {
        return $this->canManage($user) || $user->isCashier();
    }

    public function canManage(User $user): bool
    {
        return $user->isOwner() || $user->isAdmin();
    }
}
