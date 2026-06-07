<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Spatie roles that an admin account may hold.
 *
 * These are distinct from {@see UserRole} (the `users.role` column, which stays
 * `admin` for every staff account). The super/standard split is carried purely
 * by Spatie roles + named permissions, so the existing `role.admin` guard and
 * the `UserRole` cast are left untouched.
 */
enum AdminRole: string
{
    use HasValues;

    /** Full control of the platform, including managing other admins. */
    case SuperAdmin = 'super_admin';

    /** Standard admin: a configurable subset of the management permissions. */
    case Admin = 'admin';

    /**
     * Roles assignable to a staff account through the admin-management module.
     *
     * @return array<int, string>
     */
    public static function assignable(): array
    {
        return [self::SuperAdmin->value, self::Admin->value];
    }
}
