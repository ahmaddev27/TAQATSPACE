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
 * End-to-end HTTP coverage of public discovery: the index filters travel through
 * the controller's query-string parsing into the repository, the /cities
 * endpoint, and the resource visibility contract (the public payload must never
 * leak the owner/admin-only publish + messaging block). The service-level filter
 * unit tests live in WorkspaceDiscoveryTest; these assert the wiring instead.
 */
class WorkspaceDiscoveryEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function publicWorkspace(array $attributes = []): Workspace
    {
        return Workspace::factory()->create(array_merge([
            'status' => WorkspaceStatus::Active->value,
            'published_at' => now(),
        ], $attributes));
    }

    public function test_index_filters_by_price_via_query_string(): void
    {
        $this->publicWorkspace(['price_per_month' => 20]);
        $this->publicWorkspace(['price_per_month' => 80]);

        $this->getJson('/api/workspaces?min_price=50&max_price=100')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.data.0.price_per_month', '80.00');
    }

    public function test_index_search_filters_by_name(): void
    {
        $this->publicWorkspace(['name' => 'Quiet Corner']);
        $this->publicWorkspace(['name' => 'Loud Place']);

        $this->getJson('/api/workspaces?search=quiet')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.data.0.name', 'Quiet Corner');
    }

    public function test_index_sorts_by_price_ascending_via_query_string(): void
    {
        $this->publicWorkspace(['price_per_month' => 90]);
        $this->publicWorkspace(['price_per_month' => 10]);

        $this->getJson('/api/workspaces?sort=price_asc')
            ->assertOk()
            ->assertJsonPath('data.data.0.price_per_month', '10.00');
    }

    public function test_index_filters_by_amenities(): void
    {
        $this->publicWorkspace(['amenities' => ['wifi', 'coffee']]);
        $this->publicWorkspace(['amenities' => ['parking']]);

        $this->getJson('/api/workspaces?'.http_build_query(['amenities' => ['wifi', 'coffee']]))
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);
    }

    public function test_public_index_payload_hides_owner_only_fields(): void
    {
        $this->publicWorkspace();

        $response = $this->getJson('/api/workspaces')->assertOk();

        $row = $response->json('data.data.0');
        $this->assertArrayNotHasKey('is_published', $row);
        $this->assertArrayNotHasKey('messaging', $row);
    }

    public function test_public_show_payload_hides_owner_only_fields(): void
    {
        $workspace = $this->publicWorkspace();

        $row = $this->getJson("/api/workspaces/{$workspace->id}")->assertOk()->json('data');

        $this->assertArrayNotHasKey('is_published', $row);
        $this->assertArrayNotHasKey('messaging', $row);
    }

    public function test_show_payload_exposes_publish_block_to_the_owner(): void
    {
        $owner = User::factory()->owner()->create();
        $workspace = $this->publicWorkspace(['owner_id' => $owner->id]);
        Sanctum::actingAs($owner);

        $this->getJson("/api/workspaces/{$workspace->id}")
            ->assertOk()
            ->assertJsonPath('data.is_published', true)
            ->assertJsonStructure(['data' => ['messaging']]);
    }

    public function test_show_returns_404_for_unknown_id(): void
    {
        $this->getJson('/api/workspaces/non-existent-id')->assertNotFound();
    }

    public function test_cities_endpoint_lists_only_public_cities(): void
    {
        $gaza = City::create(['name_ar' => 'غزة', 'name_en' => 'Gaza', 'is_active' => true]);
        $this->publicWorkspace(['city' => $gaza->name_ar, 'city_id' => $gaza->id]);
        Workspace::factory()->create([
            'status' => WorkspaceStatus::Active->value,
            'published_at' => null,
            'city' => 'Hidden City',
        ]);

        $cities = $this->getJson('/api/workspaces/cities')->assertOk()->json('data');

        $this->assertContains('غزة', $cities);
        $this->assertNotContains('Hidden City', $cities);
    }
}
