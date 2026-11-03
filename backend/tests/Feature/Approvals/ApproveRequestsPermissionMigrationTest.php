<?php

declare(strict_types=1);

namespace Tests\Feature\Approvals;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApproveRequestsPermissionMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_creates_permission_and_grants_it_to_super_admin_without_seeder(): void
    {
        $migration = require database_path('migrations/2026_07_10_110000_seed_approve_requests_permission.php');

        Permission::where('name', 'approve_requests')->delete();
        $migration->up();
        $migration->up();

        $this->assertSame(1, Permission::where('name', 'approve_requests')->count());
        $this->assertTrue(Role::findByName('super_admin', 'web')->hasPermissionTo('approve_requests'));
    }
}
