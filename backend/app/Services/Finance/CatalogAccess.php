<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\FinancePermission;
use App\Models\User;

/**
 * Who may touch the finance catalog and the unbilled charges. Owners (and
 * platform admins) always may; a cashier may read them, change the catalog with
 * `finance_items` and record or void on-account charges with `finance_withdrawals`
 * (the legacy `pos_manage_products` grant still satisfies both). Everyone else
 * has no surface here.
 */
final class CatalogAccess
{
    public function __construct(private readonly FinanceAccess $finance) {}

    public function canView(User $user): bool
    {
        return $user->isOwner() || $user->isAdmin() || $user->isCashier();
    }

    public function canManage(User $user): bool
    {
        return $this->finance->can($user, FinancePermission::Items);
    }

    public function canManageCharges(User $user): bool
    {
        return $this->finance->can($user, FinancePermission::Withdrawals);
    }
}
