<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\PosPermission;
use App\Models\User;

/**
 * Who may touch the finance catalog. Owners (and platform admins) always may;
 * a cashier may read it, and change it only with the `pos_manage_products`
 * grant. Everyone else has no catalog surface.
 */
final class CatalogAccess
{
    public function canView(User $user): bool
    {
        return $user->isOwner() || $user->isAdmin() || $user->isCashier();
    }

    public function canManage(User $user): bool
    {
        if ($user->isOwner() || $user->isAdmin()) {
            return true;
        }

        return $user->isCashier() && $user->can(PosPermission::ManageProducts->value);
    }
}
