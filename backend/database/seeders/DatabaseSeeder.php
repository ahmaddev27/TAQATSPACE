<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AdminRole;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Production seed: the role/permission model plus a single platform super-admin
 * (admin@taqat.space) holding every permission. All business tables are left
 * empty by design — this is not demo data.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedRoles();
            $this->call(AdminPermissionSeeder::class);
            $this->call(PosPermissionSeeder::class);
            $this->call(CitySeeder::class);
            $this->seedAdmin();
        });

        $this->command?->info('Seeded super-admin: admin@taqat.space (password: password)');
    }

    /** Ensure every app role exists (the super/standard split is a Spatie role). */
    private function seedRoles(): void
    {
        foreach (UserRole::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }
    }

    /**
     * The single platform super-admin. The Spatie `super_admin` role — not the
     * `users.role` column — carries the elevated, all-permission grant (synced by
     * {@see AdminPermissionSeeder}).
     */
    private function seedAdmin(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'مشرف المنصّة',
            'email' => 'admin@taqat.space',
        ]);

        $admin->assignRole(AdminRole::SuperAdmin->value);
    }
}
