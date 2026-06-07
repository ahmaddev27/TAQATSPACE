<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\AdminPermission;
use App\Enums\AdminRole;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

/**
 * Admin-account administration for the super-admin module: a filtered directory
 * of staff accounts plus create/update/deactivate, with guards that prevent the
 * platform from ever losing its last active super-admin (or an operator locking
 * themselves out of the module).
 */
class AdminManagementService
{
    /**
     * Filtered, paginated directory of admin accounts (every account whose
     * `users.role` is `admin`), newest first, with roles + permissions loaded.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)
            ->latest()
            ->paginate($this->perPage($filters))
            ->withQueryString();
    }

    /**
     * Create a new, active admin account and apply its role + permission grant.
     *
     * @param  array{name: string, email: string, password: string}  $attributes
     * @param  array<int, string>|null  $permissions  Null → use the role defaults.
     */
    public function create(array $attributes, AdminRole $role, ?array $permissions): User
    {
        return DB::transaction(function () use ($attributes, $role, $permissions): User {
            $admin = User::query()->create([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'password' => Hash::make($attributes['password']),
                'role' => UserRole::Admin->value,
                'status' => UserStatus::Active->value,
                'email_verified_at' => now(),
                'onboarding_completed_at' => now(),
            ]);

            $this->applyRoleAndPermissions($admin, $role, $permissions);

            return $admin->fresh() ?? $admin;
        });
    }

    /**
     * Update an admin account: status, role, permissions and/or password. The
     * `$actor` is the authenticated super-admin performing the change; it is used
     * to block self-lockout (an operator stripping their own super-admin rights
     * or suspending their own account).
     *
     * @param  array<int, string>|null  $permissions
     */
    public function update(
        User $admin,
        User $actor,
        ?UserStatus $status,
        ?AdminRole $role,
        ?array $permissions,
        ?string $password,
    ): User {
        $this->ensureManageableAdmin($admin);
        $this->guardSelfDowngrade($admin, $actor, $status, $role, $permissions);
        $this->guardLastSuperAdmin($admin, $status, $role, $permissions);

        return DB::transaction(function () use ($admin, $status, $role, $permissions, $password): User {
            if ($status !== null) {
                $admin->forceFill(['status' => $status->value])->save();
            }

            if ($password !== null) {
                $admin->forceFill(['password' => Hash::make($password)])->save();
            }

            if ($role !== null) {
                $admin->syncRoles([$role->value]);
            }

            // Permissions are only touched when the caller sent the field. When a
            // role change arrives without an explicit set, fall back to that
            // role's defaults so the grant stays coherent with the new role.
            if ($permissions !== null) {
                $admin->syncPermissions($permissions);
            } elseif ($role !== null) {
                $admin->syncPermissions($this->defaultPermissionsFor($role));
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return $admin->fresh() ?? $admin;
        });
    }

    /**
     * Deactivate (suspend) an admin account. Suspension is preferred over a hard
     * delete so audit trails and references survive. Never deactivates the last
     * active super-admin, nor the actor's own account.
     */
    public function deactivate(User $admin, User $actor): User
    {
        $this->ensureManageableAdmin($admin);

        if ($admin->is($actor)) {
            abort(422, __('messages.admin_cannot_deactivate_self'));
        }

        $this->guardLastSuperAdmin($admin, UserStatus::Suspended, null, null);

        $admin->forceFill(['status' => UserStatus::Suspended->value])->save();

        return $admin->fresh() ?? $admin;
    }

    /**
     * The admin-management module only operates on staff accounts. Reject (404)
     * any attempt to manage a freelancer/owner reached through the route binding.
     */
    private function ensureManageableAdmin(User $admin): void
    {
        if ($admin->role !== UserRole::Admin) {
            abort(404, __('messages.admin_not_found'));
        }
    }

    /**
     * Apply a role and its permission grant to a fresh or existing admin.
     *
     * @param  array<int, string>|null  $permissions
     */
    private function applyRoleAndPermissions(User $admin, AdminRole $role, ?array $permissions): void
    {
        $admin->syncRoles([$role->value]);
        $admin->syncPermissions($permissions ?? $this->defaultPermissionsFor($role));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * The default permission set for a role: every permission for a super-admin,
     * the curated subset for a standard admin.
     *
     * @return array<int, string>
     */
    private function defaultPermissionsFor(AdminRole $role): array
    {
        return $role === AdminRole::SuperAdmin
            ? AdminPermission::values()
            : AdminPermission::defaultsForAdmin();
    }

    /**
     * Block an operator from removing their own ability to manage admins —
     * whether by suspending themselves, demoting from super-admin, or dropping
     * the `manage_admins` permission.
     *
     * @param  array<int, string>|null  $permissions
     */
    private function guardSelfDowngrade(
        User $admin,
        User $actor,
        ?UserStatus $status,
        ?AdminRole $role,
        ?array $permissions,
    ): void {
        if (! $admin->is($actor)) {
            return;
        }

        if ($status !== null && $status !== UserStatus::Active) {
            abort(422, __('messages.admin_cannot_suspend_self'));
        }

        $losesManageAdmins = ($role !== null && $role !== AdminRole::SuperAdmin)
            || ($permissions !== null && ! in_array(AdminPermission::ManageAdmins->value, $permissions, true));

        if ($losesManageAdmins) {
            abort(422, __('messages.admin_cannot_revoke_own_access'));
        }
    }

    /**
     * Prevent an edit/deactivation from removing the platform's last active
     * super-admin. A change "loses" super-admin when it suspends the account,
     * demotes it from super-admin, or revokes the `manage_admins` permission.
     *
     * @param  array<int, string>|null  $permissions
     */
    private function guardLastSuperAdmin(
        User $admin,
        ?UserStatus $status,
        ?AdminRole $role,
        ?array $permissions,
    ): void {
        if (! $admin->isSuperAdmin() || ! $admin->isActive()) {
            return;
        }

        $becomesInactive = $status !== null && $status !== UserStatus::Active;
        $losesRole = $role !== null && $role !== AdminRole::SuperAdmin;
        $losesPermission = $permissions !== null
            && ! in_array(AdminPermission::ManageAdmins->value, $permissions, true);

        if (! $becomesInactive && ! $losesRole && ! $losesPermission) {
            return;
        }

        if ($this->activeSuperAdminCount() <= 1) {
            abort(422, __('messages.admin_last_super_admin'));
        }
    }

    /**
     * Count of active accounts holding the super-admin role (excludes the cached
     * model state so the check reflects the database).
     */
    private function activeSuperAdminCount(): int
    {
        return User::query()
            ->where('status', UserStatus::Active->value)
            ->whereHas('roles', fn (Builder $query) => $query
                ->where('name', AdminRole::SuperAdmin->value))
            ->count();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<User>
     */
    private function filteredQuery(array $filters): Builder
    {
        $query = User::query()
            ->where('role', UserRole::Admin->value)
            ->with(['roles:id,name', 'permissions:id,name']);

        if (! empty($filters['status']) && UserStatus::tryFrom((string) $filters['status']) !== null) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $like = '%'.trim((string) $filters['search']).'%';

            $query->where(function (Builder $inner) use ($like): void {
                $inner->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function perPage(array $filters): int
    {
        $perPage = (int) ($filters['per_page'] ?? 15);

        return max(1, min($perPage, 200));
    }
}
