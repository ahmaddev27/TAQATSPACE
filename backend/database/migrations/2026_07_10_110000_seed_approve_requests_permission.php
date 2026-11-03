<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Guarantees the `approve_requests` permission exists on every environment.
 *
 * Deploys run `php artisan migrate --force` but NOT the seeders, so without this
 * the permission row is missing and `can.permission:approve_requests` answers 403
 * even for super admins, while the approvers lookup finds nobody to notify.
 * Idempotent (find-or-create); strings are hard-coded so the migration stays a
 * stable snapshot independent of later enum changes.
 */
return new class extends Migration
{
    private const PERMISSION = 'approve_requests';

    private const ROLE = 'super_admin';

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::findOrCreate(self::PERMISSION, 'web');
        Role::findOrCreate(self::ROLE, 'web')->givePermissionTo($permission);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Non-destructive: accounts may hold the permission. Nothing to reverse.
    }
};
