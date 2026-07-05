<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PosPermission;
use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the cashier role + POS permissions idempotently (mirrors
 * {@see AdminPermissionSeeder}).
 *
 * The `cashier` role grants NOTHING by itself: each cashier's effective access
 * is its own DIRECT per-account grant, assigned when the owner invites them
 * (defaulting to {@see PosPermission::defaultsForCashier()}). This keeps a
 * "sell-only" cashier genuinely narrow.
 */
class PosPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        collect(PosPermission::values())
            ->each(fn (string $name): Permission => Permission::findOrCreate($name, 'web'));

        Role::findOrCreate(UserRole::Cashier->value, 'web')->syncPermissions([]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
