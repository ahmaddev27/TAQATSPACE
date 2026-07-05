<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Guarantees the `cashier` Spatie role + POS permissions exist on every
 * environment.
 *
 * Deploys run `php artisan migrate --force` but NOT the seeders, so the role
 * added with the POS feature was missing in production — accepting a cashier
 * invitation (`syncRoles(['cashier'])`) then failed with "There is no role
 * named `cashier`". Seeding it from a migration makes it deploy-safe. Idempotent
 * (find-or-create), so it is safe to re-run and never duplicates rows. Strings
 * are hard-coded so this migration stays a stable historical snapshot,
 * independent of later enum changes.
 */
return new class extends Migration
{
    private const ROLE = 'cashier';

    private const PERMISSIONS = [
        'pos_sell',
        'pos_refund',
        'pos_manage_products',
        'pos_view_reports',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // The role intentionally grants nothing by itself — each cashier's access
        // is its own direct per-account grant (mirrors the admin tier).
        Role::findOrCreate(self::ROLE, 'web');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Non-destructive: leave the role/permissions in place (accounts may hold
        // them). Nothing to reverse.
    }
};
