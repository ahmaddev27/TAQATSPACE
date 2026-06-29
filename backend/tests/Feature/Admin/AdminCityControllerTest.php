<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\City;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminCityControllerTest extends TestCase
{
    use RefreshDatabase;

    private function contentAdmin(): User
    {
        $admin = User::factory()->admin()->create();
        Permission::findOrCreate('manage_content', 'web');
        $admin->givePermissionTo('manage_content');

        return $admin;
    }

    public function test_index_returns_cities_with_workspace_counts(): void
    {
        Sanctum::actingAs($this->contentAdmin());
        $city = City::create(['name_ar' => 'الرياض', 'name_en' => 'Riyadh', 'is_active' => true]);
        Workspace::factory()->count(2)->create(['city_id' => $city->id]);

        $this->getJson('/api/admin/cities')
            ->assertOk()
            ->assertJsonPath('data.0.name_en', 'Riyadh')
            ->assertJsonPath('data.0.workspaces_count', 2);
    }

    public function test_store_creates_a_city(): void
    {
        Sanctum::actingAs($this->contentAdmin());

        $this->postJson('/api/admin/cities', [
            'name_ar' => 'تبوك',
            'name_en' => 'Tabuk',
            'is_active' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.name_en', 'Tabuk');

        $this->assertDatabaseHas('cities', ['name_en' => 'Tabuk']);
    }

    public function test_store_validates_required_fields(): void
    {
        Sanctum::actingAs($this->contentAdmin());

        $this->postJson('/api/admin/cities', ['name_ar' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name_ar', 'name_en']);
    }

    public function test_update_renames_city_and_propagates_to_workspaces(): void
    {
        Sanctum::actingAs($this->contentAdmin());
        $city = City::create(['name_ar' => 'القديمة', 'name_en' => 'Old', 'is_active' => true]);
        $workspace = Workspace::factory()->create([
            'city_id' => $city->id,
            'city' => 'القديمة',
        ]);

        $this->putJson("/api/admin/cities/{$city->id}", [
            'name_ar' => 'الجديدة',
            'name_en' => 'New',
        ])
            ->assertOk()
            ->assertJsonPath('data.name_ar', 'الجديدة');

        $this->assertSame('الجديدة', $workspace->refresh()->city);
    }

    public function test_destroy_hard_deletes_an_unused_city(): void
    {
        Sanctum::actingAs($this->contentAdmin());
        $city = City::create(['name_ar' => 'غير مستخدمة', 'name_en' => 'Unused', 'is_active' => true]);

        $this->deleteJson("/api/admin/cities/{$city->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('cities', ['id' => $city->id]);
    }

    public function test_destroy_deactivates_a_referenced_city_instead_of_deleting(): void
    {
        Sanctum::actingAs($this->contentAdmin());
        $city = City::create(['name_ar' => 'مستخدمة', 'name_en' => 'Used', 'is_active' => true]);
        Workspace::factory()->create(['city_id' => $city->id]);

        $this->deleteJson("/api/admin/cities/{$city->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', false);

        $this->assertDatabaseHas('cities', ['id' => $city->id, 'is_active' => false]);
    }

    public function test_routes_reject_unauthenticated(): void
    {
        $this->getJson('/api/admin/cities')->assertUnauthorized();
    }

    public function test_non_admin_is_forbidden(): void
    {
        Sanctum::actingAs(User::factory()->owner()->create());

        $this->getJson('/api/admin/cities')->assertForbidden();
    }

    public function test_admin_without_content_permission_is_forbidden(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/admin/cities')->assertForbidden();
    }
}
