<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Named Spatie permissions that gate the finance (v2) area for workspace staff.
 *
 * Like {@see PosPermission} they are DIRECT per-account grants merged into the
 * group map of {@see StaffGroup}. The owner (and platform admins) implicitly
 * hold all of them; see FinanceAccess for the legacy POS grants that imply them.
 */
enum FinancePermission: string
{
    use HasValues;

    case ViewPos = 'finance_view_pos';
    case Withdrawals = 'finance_withdrawals';
    case Collect = 'finance_collect';
    case IssueVoid = 'finance_issue_void';
    case Expenses = 'finance_expenses';
    case Items = 'finance_items';
    case Import = 'finance_import';
    case Reports = 'finance_reports';

    /**
     * Everything a finance supervisor holds. Bulk import is optional and stays
     * an explicit owner grant.
     *
     * @return array<int, string>
     */
    public static function defaultsForFinanceSupervisor(): array
    {
        return array_values(array_diff(self::values(), [self::Import->value]));
    }

    /**
     * Permissions a cashier gets on top of the POS ones: take collections and
     * look at the POS.
     *
     * @return array<int, string>
     */
    public static function defaultsForCashier(): array
    {
        return [self::Collect->value, self::ViewPos->value];
    }
}
