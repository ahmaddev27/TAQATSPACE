<?php

declare(strict_types=1);

namespace Tests\Feature\WorkspacesExtra;

use App\Enums\WorkspaceStatus;
use App\Models\City;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Authentication + role-middleware coverage for the workspace surface — the
 * guards in front of the owner and admin route groups. The happy paths and the
 * gate-denied admin case live in tests/Feature/Workspaces; here we assert the
 * 401 (unauthenticated) and 403 (wrong role) boundaries explicitly.
 */
class WorkspaceAuthAndRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_endpoints_require_authentication(): void
    {
        $this->postJson('/api/workspace/create', [])->assertUnauthorized();
        $this->putJson('/api/workspace/settings', [])->assertUnauthorized();
        $this->postJson('/api/workspace/photos', [])->assertUnauthorized();
        $this->deleteJson('/api/workspace/photos', [])->assertUnauthorized();
        $this->postJson('/api/workspace/logo', [])->assertUnauthorized();
        $this->deleteJson('/api/workspace/logo')->assertUnauthorized();
    }

    public function test_admin_endpoints_require_authentication(): void
    {
        $workspace = Workspace::factory()->create();

        $this->getJson('/api/admin/workspaces')->assertUnauthorized();
        $this->getJson("/api/admin/workspaces/{$workspace->id}")->assertUnauthorized();
        $this->putJson("/api/admin/workspaces/{$workspace->id}/status", [])->assertUnauthorized();
        $this->putJson("/api/admin/workspaces/{$workspace->id}/publish")->assertUnauthorized();
        $this->putJson("/api/admin/workspaces/{$workspace->id}/unpublish")->assertUnauthorized();
    }

    public function test_freelancer_cannot_reach_owner_create(): void
    {
        $city = City::create(['name_ar' => 'غزة', 'name_en' => 'Gaza', 'is_active' => true]);
        Sanctum::actingAs(User::factory()->freelancer()->create());

        $this->postJson('/api/workspace/create', [
            'name' => 'No Way',
            'address' => 'Main St',
            'city_id' => $city->id,
            'total_seats' => 10,
            'price_per_month' => 30,
        ])->assertForbidden();
    }

    public function test_freelancer_cannot_reach_admin_index(): void
    {
        Sanctum::actingAs(User::factory()->freelancer()->create());

        $this->getJson('/api/admin/workspaces')->assertForbidden();
    }

    public function test_owner_without_manage_permission_cannot_reach_admin_status(): void
    {
        // An admin role without the manage_workspaces permission is stopped by
        // the can.permission middleware, not just the role gate.
        $admin = User::factory()->admin()->create();
        $workspace = Workspace::factory()->pending()->create();
        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/workspaces/{$workspace->id}/status", [
            'status' => WorkspaceStatus::Active->value,
        ])->assertForbidden();
    }
}
