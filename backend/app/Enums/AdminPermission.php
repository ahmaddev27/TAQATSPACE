<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Named Spatie permissions that gate the major admin areas.
 *
 * `super_admin` is granted every permission; `admin` receives the
 * {@see self::defaultsForAdmin()} subset by default. New admin-management
 * endpoints are gated on {@see self::ManageAdmins}; retrofitting the remaining
 * permissions onto the legacy admin routes is intentionally out of scope.
 */
enum AdminPermission: string
{
    use HasValues;

    case ManageAdmins = 'manage_admins';
    case ManageWorkspaces = 'manage_workspaces';
    case ManageUsers = 'manage_users';
    case ManageBilling = 'manage_billing';
    case ManageContent = 'manage_content';
    case ManageMessaging = 'manage_messaging';
    case ViewReports = 'view_reports';

    /**
     * Permissions a standard `admin` receives by default. Deliberately excludes
     * `manage_admins` — only a `super_admin` may administer other admins.
     *
     * @return array<int, string>
     */
    public static function defaultsForAdmin(): array
    {
        return [
            self::ManageWorkspaces->value,
            self::ManageUsers->value,
            self::ManageBilling->value,
            self::ManageContent->value,
            self::ManageMessaging->value,
            self::ViewReports->value,
        ];
    }
}
