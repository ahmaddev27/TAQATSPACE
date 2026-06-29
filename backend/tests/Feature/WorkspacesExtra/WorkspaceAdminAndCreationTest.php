<?php

declare(strict_types=1);

namespace Tests\Feature\WorkspacesExtra;

use App\Enums\SeatType;
use App\Enums\WorkspaceStatus;
use App\Models\City;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Admin listing/detail endpoints, creation edge cases (seat-type-driven
 * derivation, deeper input validation) and admin status-transition validation
 * not already covered by tests/Feature/Workspaces.
 */
class WorkspaceAdminAndCreationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->admin()->create();
        $this->grantManageWorkspaces($admin);

        return $admin;
    }

    public function test_admin_index_lists_all_statuses_with_owner(): void
    {
        Workspace::factory()->create(['status' => WorkspaceStatus::Active->value, 'published_at' => null]);
        Workspace::factory()->pending()->create();
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/workspaces')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 2)
            ->assertJsonStructure(['data' => ['data' => [['owner' => ['id', 'email']]]]]);
    }

    public function test_admin_index_filters_by_status(): void
    {
        Workspace::factory()->create(['status' => WorkspaceStatus::Active->value]);
        Workspace::factory()->pending()->create();
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/workspaces?status='.WorkspaceStatus::Pending->value)
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.data.0.status', WorkspaceStatus::Pending->value);
    }

    public function test_admin_index_filters_by_name(): void
    {
        Workspace::factory()->create(['name' => 'Alpha Hub']);
        Workspace::factory()->create(['name' => 'Beta Den']);
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/workspaces?name=Alpha')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.data.0.name', 'Alpha Hub');
    }

    public function test_admin_show_returns_detail_bundle(): void
    {
        $workspace = Workspace::factory()->create();
        Sanctum::actingAs($this->admin());

        $this->getJson("/api/admin/workspaces/{$workspace->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $workspace->id);
    }

    public function test_admin_status_rejects_an_unsupported_status_value(): void
    {
        $workspace = Workspace::factory()->pending()->create();
        Sanctum::actingAs($this->admin());

        // Pending is not an allowed transition target on this endpoint.
        $this->putJson("/api/admin/workspaces/{$workspace->id}/status", [
            'status' => WorkspaceStatus::Pending->value,
        ])->assertStatus(422)->assertJsonValidationErrors(['status']);
    }

    public function test_admin_status_requires_a_reason_when_suspending(): void
    {
        $workspace = Workspace::factory()->create(['status' => WorkspaceStatus::Active->value]);
        Sanctum::actingAs($this->admin());

        $this->putJson("/api/admin/workspaces/{$workspace->id}/status", [
            'status' => WorkspaceStatus::Suspended->value,
        ])->assertStatus(422)->assertJsonValidationErrors(['reason']);
    }

    public function test_admin_status_accepts_rejection_with_reason(): void
    {
        Notification::fake();
        $workspace = Workspace::factory()->pending()->create();
        Sanctum::actingAs($this->admin());

        $this->putJson("/api/admin/workspaces/{$workspace->id}/status", [
            'status' => WorkspaceStatus::Rejected->value,
            'reason' => 'Incomplete licensing documents.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', WorkspaceStatus::Rejected->value);
    }

    public function test_create_with_seat_types_derives_capacity_and_price(): void
    {
        $owner = User::factory()->owner()->create();
        $city = City::create(['name_ar' => 'غزة', 'name_en' => 'Gaza', 'is_active' => true]);
        Sanctum::actingAs($owner);

        $this->postJson('/api/workspace/create', [
            'name' => 'Seated Hub',
            'address' => 'Main St',
            'city_id' => $city->id,
            'total_seats' => 5,
            'price_per_month' => 999,
            'seat_types' => [
                ['type' => SeatType::values()[0], 'price_monthly' => 40, 'capacity' => 6, 'enabled' => true],
                ['type' => SeatType::values()[1], 'price_monthly' => 70, 'capacity' => 4, 'enabled' => true],
            ],
        ])->assertCreated();

        $workspace = Workspace::where('owner_id', $owner->id)->firstOrFail();

        // total_seats = sum of enabled capacity; price_per_month = lowest monthly,
        // both derived from seat types regardless of the client-sent values.
        $this->assertSame(10, $workspace->total_seats);
        $this->assertEquals(40.0, (float) $workspace->price_per_month);
    }

    public function test_create_rejects_an_invalid_seat_type(): void
    {
        $owner = User::factory()->owner()->create();
        $city = City::create(['name_ar' => 'غزة', 'name_en' => 'Gaza', 'is_active' => true]);
        Sanctum::actingAs($owner);

        $this->postJson('/api/workspace/create', [
            'name' => 'Bad Hub',
            'address' => 'Main St',
            'city_id' => $city->id,
            'total_seats' => 5,
            'price_per_month' => 30,
            'seat_types' => [
                ['type' => 'teleporter', 'capacity' => 1, 'enabled' => true],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors(['seat_types.0.type']);
    }

    public function test_create_rejects_an_unknown_city(): void
    {
        Sanctum::actingAs(User::factory()->owner()->create());

        $this->postJson('/api/workspace/create', [
            'name' => 'Ghost Hub',
            'address' => 'Main St',
            'city_id' => 'no-such-city',
            'total_seats' => 5,
            'price_per_month' => 30,
        ])->assertStatus(422)->assertJsonValidationErrors(['city_id']);
    }

    public function test_create_rejects_out_of_range_numeric_fields(): void
    {
        $city = City::create(['name_ar' => 'غزة', 'name_en' => 'Gaza', 'is_active' => true]);
        Sanctum::actingAs(User::factory()->owner()->create());

        $this->postJson('/api/workspace/create', [
            'name' => 'X',
            'address' => 'Main St',
            'city_id' => $city->id,
            'total_seats' => 0,
            'price_per_month' => -5,
            'latitude' => 200,
        ])->assertStatus(422)->assertJsonValidationErrors([
            'name', 'total_seats', 'price_per_month', 'latitude',
        ]);
    }

    public function test_update_settings_changes_city_denormalized_string(): void
    {
        $owner = User::factory()->owner()->create();
        Workspace::factory()->create(['owner_id' => $owner->id, 'city' => 'Old City']);
        $city = City::create(['name_ar' => 'رام الله', 'name_en' => 'Ramallah', 'is_active' => true]);
        Sanctum::actingAs($owner);

        $this->putJson('/api/workspace/settings', ['city_id' => $city->id])
            ->assertOk()
            ->assertJsonPath('data.city', 'رام الله')
            ->assertJsonPath('data.city_id', $city->id);
    }

    private function grantManageWorkspaces(User $admin): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permission = Permission::findOrCreate('manage_workspaces', 'web');
        $admin->givePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
