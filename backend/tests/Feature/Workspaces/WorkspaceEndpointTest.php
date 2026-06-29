<?php

declare(strict_types=1);

namespace Tests\Feature\Workspaces;

use App\Enums\WorkspaceStatus;
use App\Models\City;
use App\Models\Review;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * HTTP coverage of the WorkspaceController: public show/index, owner
 * create/update(settings), and admin publish/unpublish/status — asserting the
 * { data, message } envelope, status codes, and gate enforcement.
 */
class WorkspaceEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function publicWorkspace(array $attributes = []): Workspace
    {
        return Workspace::factory()->create(array_merge([
            'status' => WorkspaceStatus::Active->value,
            'published_at' => now(),
        ], $attributes));
    }

    public function test_public_index_lists_visible_workspaces(): void
    {
        $this->publicWorkspace();
        Workspace::factory()->create(['status' => WorkspaceStatus::Active->value, 'published_at' => null]);

        $this->getJson('/api/workspaces')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);
    }

    public function test_public_show_returns_detail_bundle(): void
    {
        $workspace = $this->publicWorkspace();
        Review::factory()->create(['workspace_id' => $workspace->id, 'rating' => 5]);

        $this->getJson("/api/workspaces/{$workspace->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $workspace->id)
            ->assertJsonStructure(['data' => ['seats_summary', 'recent_reviews']]);
    }

    public function test_public_show_404s_for_unpublished(): void
    {
        $hidden = Workspace::factory()->create([
            'status' => WorkspaceStatus::Active->value,
            'published_at' => null,
        ]);

        $this->getJson("/api/workspaces/{$hidden->id}")->assertNotFound();
    }

    public function test_owner_can_create_a_workspace(): void
    {
        $owner = User::factory()->owner()->create();
        $city = City::create(['name_ar' => 'غزة', 'name_en' => 'Gaza', 'is_active' => true]);
        Sanctum::actingAs($owner);

        $this->postJson('/api/workspace/create', [
            'name' => 'My Hub',
            'address' => 'Main St',
            'city_id' => $city->id,
            'total_seats' => 10,
            'price_per_month' => 30,
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'My Hub')
            ->assertJsonPath('data.status', WorkspaceStatus::Pending->value);
    }

    public function test_owner_cannot_create_a_second_workspace(): void
    {
        $owner = User::factory()->owner()->create();
        $city = City::create(['name_ar' => 'غزة', 'name_en' => 'Gaza', 'is_active' => true]);
        Workspace::factory()->create(['owner_id' => $owner->id]);
        Sanctum::actingAs($owner);

        $this->postJson('/api/workspace/create', [
            'name' => 'Another',
            'address' => 'Main St',
            'city_id' => $city->id,
            'total_seats' => 10,
            'price_per_month' => 30,
        ])->assertStatus(409);
    }

    public function test_create_validates_required_fields(): void
    {
        Sanctum::actingAs(User::factory()->owner()->create());

        $this->postJson('/api/workspace/create', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'address', 'city_id']);
    }

    public function test_owner_can_update_settings(): void
    {
        $owner = User::factory()->owner()->create();
        Workspace::factory()->create(['owner_id' => $owner->id, 'name' => 'Old']);
        Sanctum::actingAs($owner);

        $this->putJson('/api/workspace/settings', ['name' => 'Updated'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated');
    }

    public function test_update_settings_404s_without_a_workspace(): void
    {
        Sanctum::actingAs(User::factory()->owner()->create());

        $this->putJson('/api/workspace/settings', ['name' => 'Updated'])->assertNotFound();
    }

    public function test_admin_can_publish_and_unpublish(): void
    {
        $admin = User::factory()->admin()->create();
        $this->grantManageWorkspaces($admin);
        $workspace = Workspace::factory()->create(['published_at' => null]);
        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/workspaces/{$workspace->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.is_published', true);

        $this->putJson("/api/admin/workspaces/{$workspace->id}/unpublish")
            ->assertOk()
            ->assertJsonPath('data.is_published', false);
    }

    public function test_admin_can_change_status(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $this->grantManageWorkspaces($admin);
        $workspace = Workspace::factory()->pending()->create();
        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/workspaces/{$workspace->id}/status", [
            'status' => WorkspaceStatus::Active->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', WorkspaceStatus::Active->value);
    }

    public function test_admin_status_requires_a_reason_when_rejecting(): void
    {
        $admin = User::factory()->admin()->create();
        $this->grantManageWorkspaces($admin);
        $workspace = Workspace::factory()->pending()->create();
        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/workspaces/{$workspace->id}/status", [
            'status' => WorkspaceStatus::Rejected->value,
        ])->assertStatus(422)->assertJsonValidationErrors(['reason']);
    }

    public function test_owner_cannot_reach_admin_publish(): void
    {
        $owner = User::factory()->owner()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        Sanctum::actingAs($owner);

        $this->putJson("/api/admin/workspaces/{$workspace->id}/publish")->assertForbidden();
    }

    /**
     * Give an admin the manage_workspaces Spatie permission required by the
     * admin routes (can.permission middleware). The permission is found-or-
     * created on the same `web` guard the app seeds it on.
     */
    private function grantManageWorkspaces(User $admin): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::findOrCreate('manage_workspaces', 'web');
        $admin->givePermissionTo($permission);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
