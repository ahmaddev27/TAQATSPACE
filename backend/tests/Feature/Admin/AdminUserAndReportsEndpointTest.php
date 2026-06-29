<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserStatus;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\AccountStatusChangedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminUserAndReportsEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function adminWith(string $permission): User
    {
        $admin = User::factory()->admin()->create();
        Permission::findOrCreate($permission, 'web');
        $admin->givePermissionTo($permission);

        return $admin;
    }

    // --- User directory (manage_users) -------------------------------------

    public function test_user_index_lists_members_only(): void
    {
        Sanctum::actingAs($this->adminWith('manage_users'));
        $freelancer = User::factory()->freelancer()->create();
        $staff = User::factory()->admin()->create();

        $response = $this->getJson('/api/admin/users')->assertOk();

        $ids = array_column($response->json('data.data'), 'id');
        $this->assertContains($freelancer->id, $ids);
        $this->assertNotContains($staff->id, $ids);
    }

    public function test_user_show_returns_detail(): void
    {
        Sanctum::actingAs($this->adminWith('manage_users'));
        $member = User::factory()->freelancer()->create(['name' => 'Detail Member']);

        $this->getJson("/api/admin/users/{$member->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Detail Member');
    }

    public function test_update_status_suspends_a_member_and_notifies(): void
    {
        Notification::fake();
        Sanctum::actingAs($this->adminWith('manage_users'));
        $member = User::factory()->freelancer()->create();

        $this->putJson("/api/admin/users/{$member->id}/status", [
            'status' => UserStatus::Suspended->value,
        ])->assertOk();

        $this->assertSame(UserStatus::Suspended, $member->refresh()->status);
        Notification::assertSentTo($member, AccountStatusChangedNotification::class);
    }

    public function test_suspending_an_owner_unpublishes_their_workspace(): void
    {
        Notification::fake();
        Sanctum::actingAs($this->adminWith('manage_users'));
        $owner = User::factory()->owner()->create();
        $workspace = Workspace::factory()->create([
            'owner_id' => $owner->id,
            'published_at' => now(),
        ]);

        $this->putJson("/api/admin/users/{$owner->id}/status", [
            'status' => UserStatus::Suspended->value,
        ])->assertOk();

        $this->assertNull($workspace->refresh()->published_at);
    }

    public function test_update_status_rejects_modifying_a_staff_admin(): void
    {
        Sanctum::actingAs($this->adminWith('manage_users'));
        $target = User::factory()->admin()->create();

        $this->putJson("/api/admin/users/{$target->id}/status", [
            'status' => UserStatus::Suspended->value,
        ])->assertForbidden();
    }

    public function test_update_status_validates_status_enum(): void
    {
        Sanctum::actingAs($this->adminWith('manage_users'));
        $member = User::factory()->freelancer()->create();

        $this->putJson("/api/admin/users/{$member->id}/status", [
            'status' => 'not-a-status',
        ])->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_user_routes_forbidden_without_manage_users(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/admin/users')->assertForbidden();
    }

    // --- Reports / Analytics (view_reports) --------------------------------

    public function test_analytics_endpoint_returns_breakdowns(): void
    {
        Sanctum::actingAs($this->adminWith('view_reports'));
        Workspace::factory()->create(['city' => 'Riyadh']);

        $this->getJson('/api/admin/analytics')
            ->assertOk()
            ->assertJsonStructure(['data' => ['by_city', 'by_gender']]);
    }

    public function test_reports_forbidden_without_view_reports(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/admin/reports')->assertForbidden();
        $this->getJson('/api/admin/analytics')->assertForbidden();
    }

    // --- Permission middleware edge cases ----------------------------------

    public function test_suspended_admin_is_forbidden_even_with_permission(): void
    {
        $admin = $this->adminWith('view_reports');
        $admin->forceFill(['status' => UserStatus::Suspended->value])->save();
        Sanctum::actingAs($admin->refresh());

        $this->getJson('/api/admin/reports')->assertForbidden();
    }

    // --- Dashboard stats ---------------------------------------------------

    public function test_stats_endpoint_returns_counters(): void
    {
        Sanctum::actingAs($this->adminWith('view_reports'));
        Subscription::factory()->create();

        $this->getJson('/api/admin/stats')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['workspaces', 'users', 'subscriptions', 'invoices', 'revenue'],
            ]);
    }
}
