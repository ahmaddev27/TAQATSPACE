<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\AdminPermission;
use App\Enums\AdminRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminManagementControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (AdminRole::assignable() as $role) {
            Role::findOrCreate($role, 'web');
        }
        foreach (AdminPermission::values() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }

    /**
     * A super-admin able to manage admins. The FIRST super-admin created becomes
     * the platform's protected owner — fine as the acting account here.
     */
    private function superAdmin(): User
    {
        $admin = User::factory()->admin()->create();
        $admin->syncRoles([AdminRole::SuperAdmin->value]);
        $admin->syncPermissions(AdminPermission::values());

        return $admin;
    }

    private function standardAdmin(): User
    {
        $admin = User::factory()->admin()->create();
        $admin->syncRoles([AdminRole::Admin->value]);
        $admin->syncPermissions(AdminPermission::defaultsForAdmin());

        return $admin;
    }

    public function test_index_excludes_the_protected_super_admin(): void
    {
        $actor = $this->superAdmin();
        $other = $this->standardAdmin();
        Sanctum::actingAs($actor);

        $response = $this->getJson('/api/admin/admins')->assertOk();

        $ids = array_column($response->json('data.data'), 'id');
        $this->assertContains($other->id, $ids);
        $this->assertNotContains($actor->id, $ids);
    }

    public function test_store_creates_an_admin_with_role_defaults(): void
    {
        Sanctum::actingAs($this->superAdmin());

        $this->postJson('/api/admin/admins', [
            'name' => 'New Admin',
            'email' => 'newadmin@mail.ps',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'admin_role' => AdminRole::Admin->value,
        ])->assertCreated()
            ->assertJsonPath('data.email', 'newadmin@mail.ps');

        $created = User::where('email', 'newadmin@mail.ps')->firstOrFail();
        $this->assertTrue($created->hasRole(AdminRole::Admin->value));
        $this->assertTrue($created->can(AdminPermission::ManageBilling->value));
        $this->assertFalse($created->can(AdminPermission::ManageAdmins->value));
    }

    public function test_store_validates_unique_email_and_confirmed_password(): void
    {
        Sanctum::actingAs($this->superAdmin());
        User::factory()->create(['email' => 'taken@mail.ps']);

        $this->postJson('/api/admin/admins', [
            'name' => 'Dup',
            'email' => 'taken@mail.ps',
            'password' => 'Password123!',
            'password_confirmation' => 'mismatch',
            'admin_role' => AdminRole::Admin->value,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_update_changes_status_of_another_admin(): void
    {
        Sanctum::actingAs($this->superAdmin());
        $target = $this->standardAdmin();

        $this->putJson("/api/admin/admins/{$target->id}", [
            'status' => UserStatus::Suspended->value,
        ])->assertOk();

        $this->assertSame(UserStatus::Suspended, $target->refresh()->status);
    }

    public function test_update_returns_404_for_a_non_admin_target(): void
    {
        Sanctum::actingAs($this->superAdmin());
        $freelancer = User::factory()->freelancer()->create();

        $this->putJson("/api/admin/admins/{$freelancer->id}", [
            'status' => UserStatus::Suspended->value,
        ])->assertNotFound();
    }

    public function test_super_admin_cannot_suspend_self(): void
    {
        // A second super-admin so the actor isn't the protected one and the
        // last-super-admin guard is not what trips here.
        $this->superAdmin();
        $actor = $this->superAdmin();
        $actor->syncRoles([AdminRole::SuperAdmin->value]);
        $actor->syncPermissions(AdminPermission::values());
        Sanctum::actingAs($actor);

        $this->putJson("/api/admin/admins/{$actor->id}", [
            'status' => UserStatus::Suspended->value,
        ])->assertStatus(422);
    }

    public function test_cannot_remove_the_last_super_admin(): void
    {
        $actor = $this->superAdmin();
        $target = $this->standardAdmin();
        // Promote target to a second super-admin via the actor.
        Sanctum::actingAs($actor);
        $target->syncRoles([AdminRole::SuperAdmin->value]);
        $target->syncPermissions(AdminPermission::values());

        // Now demote the target back; one super-admin (actor) remains, so allowed.
        $this->putJson("/api/admin/admins/{$target->id}", [
            'admin_role' => AdminRole::Admin->value,
        ])->assertOk();

        $this->assertFalse($target->refresh()->hasRole(AdminRole::SuperAdmin->value));
    }

    public function test_destroy_deactivates_an_admin(): void
    {
        Sanctum::actingAs($this->superAdmin());
        $target = $this->standardAdmin();

        $this->deleteJson("/api/admin/admins/{$target->id}")->assertOk();

        $this->assertSame(UserStatus::Suspended, $target->refresh()->status);
    }

    public function test_standard_admin_without_manage_admins_is_forbidden(): void
    {
        Sanctum::actingAs($this->standardAdmin());

        $this->getJson('/api/admin/admins')->assertForbidden();
    }

    public function test_routes_reject_unauthenticated(): void
    {
        $this->getJson('/api/admin/admins')->assertUnauthorized();
    }

    public function test_non_admin_role_is_forbidden(): void
    {
        Sanctum::actingAs(User::factory()->owner()->create());

        $this->getJson('/api/admin/admins')->assertForbidden();
    }
}
