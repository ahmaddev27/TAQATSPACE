<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AdminPermission;
use App\Enums\AdminRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the admin role/permission model idempotently.
 *
 * Safe to re-run: roles and permissions are found-or-created and grants are
 * synced (not appended), so repeated runs converge to the same state without
 * duplicating rows or drifting. Invoked from {@see DatabaseSeeder} and runnable
 * standalone via `php artisan db:seed --class=AdminPermissionSeeder`.
 */
class AdminPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // A stale permission cache can hide rows created in the same process,
        // so reset it before touching the model.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = $this->ensurePermissions();

        $superAdmin = Role::findOrCreate(AdminRole::SuperAdmin->value, 'web');
        $admin = Role::findOrCreate(AdminRole::Admin->value, 'web');

        // super_admin holds every permission; admin holds the curated default
        // subset (everything except manage_admins). syncPermissions makes both
        // grants declarative — re-running prunes anything no longer listed.
        $superAdmin->syncPermissions($permissions->all());
        $admin->syncPermissions(AdminPermission::defaultsForAdmin());

        $this->ensureSuperAdminExists();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Guarantee at least one active super-admin so the management module is never
     * orphaned. If none exists yet, promote the seeded platform admin
     * (admin@taqat.space). Safe to re-run: once a super-admin is present this is
     * a no-op. This also upgrades databases seeded before the super-admin role
     * existed, without requiring a fresh migration.
     */
    private function ensureSuperAdminExists(): void
    {
        $hasSuperAdmin = User::query()
            ->where('status', UserStatus::Active->value)
            ->whereHas('roles', fn ($query) => $query->where('name', AdminRole::SuperAdmin->value))
            ->exists();

        if ($hasSuperAdmin) {
            return;
        }

        $admin = User::query()->where('email', 'admin@taqat.space')->first();

        $admin?->assignRole(AdminRole::SuperAdmin->value);
    }

    /**
     * Find-or-create every named permission.
     *
     * @return \Illuminate\Support\Collection<int, Permission>
     */
    private function ensurePermissions(): \Illuminate\Support\Collection
    {
        return collect(AdminPermission::values())
            ->map(fn (string $name): Permission => Permission::findOrCreate($name, 'web'));
    }
}
