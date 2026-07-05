<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Named Spatie permissions that gate the POS (café) area of a workspace.
 *
 * A cashier is bound to exactly one workspace via `users.workspace_id`; these
 * permissions are granted as DIRECT per-account grants (the `cashier` role
 * itself grants nothing), so an owner can hand a staff member only the slice of
 * the POS they should touch — mirroring the admin permission tier.
 */
enum PosPermission: string
{
    use HasValues;

    case Sell = 'pos_sell';
    case Refund = 'pos_refund';
    case ManageProducts = 'pos_manage_products';
    case ViewReports = 'pos_view_reports';

    /**
     * Permissions a freshly-invited cashier receives by default: run sales and
     * see their own POS reports, but not issue refunds or edit the catalogue
     * until the owner grants those explicitly.
     *
     * @return array<int, string>
     */
    public static function defaultsForCashier(): array
    {
        return [
            self::Sell->value,
            self::ViewReports->value,
        ];
    }
}
