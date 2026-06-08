<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Named Spatie permissions that gate the major admin areas.
 *
 * `super_admin` is granted every permission (via its role); a standard `admin`
 * receives the {@see self::defaultsForAdmin()} subset by default — assigned as
 * DIRECT per-account grants, so a limited admin is genuinely restricted (the
 * `admin` role itself grants nothing). Every admin route group is gated by its
 * permission via `can.permission:<perm>` (e.g. billing → manage_billing).
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
