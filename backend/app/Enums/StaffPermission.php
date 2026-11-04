<?php

declare(strict_types=1);

namespace App\Enums;

/** Every permission name an owner may grant to a staff member (POS + finance). */
final class StaffPermission
{
    /** @return array<int, string> */
    public static function values(): array
    {
        return array_merge(PosPermission::values(), FinancePermission::values());
    }
}
