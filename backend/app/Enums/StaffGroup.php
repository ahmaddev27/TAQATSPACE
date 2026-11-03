<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Predefined workspace-staff permission groups. {@see self::permissions()} is
 * the SINGLE group → permissions map: later modules (e.g. finance permissions
 * for the finance supervisor) extend it here without touching any UI or service.
 *
 * All staff share the technical `cashier` role; the group only decides which
 * direct permissions are granted by default (the owner can then fine-tune them
 * per user).
 */
enum StaffGroup: string
{
    use HasValues;

    case Cashier = 'cashier';
    case Hospitality = 'hospitality';
    case FinanceSupervisor = 'finance_supervisor';

    /**
     * Permission names granted to a member of this group.
     *
     * @return array<int, string>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Cashier => [
                PosPermission::Sell->value,
                PosPermission::ViewReports->value,
            ],
            self::Hospitality => [
                PosPermission::Sell->value,
                PosPermission::ManageProducts->value,
            ],
            self::FinanceSupervisor => [
                PosPermission::Refund->value,
                PosPermission::ViewReports->value,
            ],
        };
    }
}
