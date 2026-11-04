<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Guarantees the finance staff permissions exist on every environment (deploys
 * run migrations, not seeders). Idempotent find-or-create; strings are
 * hard-coded so this stays a stable snapshot independent of later enum changes.
 * Existing staff keep exactly the grants they hold today.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'finance_view_pos',
        'finance_withdrawals',
        'finance_collect',
        'finance_issue_void',
        'finance_expenses',
        'finance_items',
        'finance_import',
        'finance_reports',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Non-destructive: accounts may hold these permissions.
    }
};
