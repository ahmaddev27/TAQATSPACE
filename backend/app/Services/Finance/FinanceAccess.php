<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\FinancePermission;
use App\Enums\PosPermission;
use App\Models\User;

/**
 * Single answer to "may this user do that in the workspace finance area".
 *
 * Owners and platform admins hold every finance permission. Staff hold only
 * their direct grants, plus the legacy POS grants that already covered the same
 * ground before the finance permissions existed, so old cashiers keep working.
 * Finance routes bind a permission with the `finance.permission:<name>` alias.
 */
final class FinanceAccess
{
    /** Legacy POS grants that also satisfy a finance permission. */
    private const IMPLIED_BY = [
        'finance_view_pos' => [PosPermission::Sell],
        'finance_withdrawals' => [PosPermission::ManageProducts],
        'finance_items' => [PosPermission::ManageProducts],
        'finance_reports' => [PosPermission::ViewReports],
    ];

    public function can(User $user, FinancePermission $permission): bool
    {
        if ($user->isOwner() || $user->isAdmin()) {
            return true;
        }

        if (! $user->isCashier()) {
            return false;
        }

        $legacy = array_map(
            static fn (PosPermission $pos): string => $pos->value,
            self::IMPLIED_BY[$permission->value] ?? [],
        );

        return $user->hasAnyPermission([$permission->value, ...$legacy]);
    }
}
