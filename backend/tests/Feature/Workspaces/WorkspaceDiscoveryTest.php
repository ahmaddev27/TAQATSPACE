<?php

declare(strict_types=1);

namespace Tests\Feature\Workspaces;

use App\Enums\WorkspaceStatus;
use App\Models\City;
use App\Models\Workspace;
use App\Services\WorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Public discovery: only active+published workspaces appear, and the filter set
 * (price, amenities, rating, search) plus sorting behave as the repository
 * declares. City matching basics are covered in CityFilterTest.
 */
class WorkspaceDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    private function service(): WorkspaceService
    {
        return app(WorkspaceService::class);
    }

    /** A publicly-visible workspace = active AND published. */
    private function publicWorkspace(array $attributes = []): Workspace
    {
        return Workspace::factory()->create(array_merge([
            'status' => WorkspaceStatus::Active->value,
            'published_at' => now(),
        ], $attributes));
    }

    public function test_discover_returns_only_active_and_published(): void
    {
        $this->publicWorkspace();
        Workspace::factory()->create(['status' => WorkspaceStatus::Active->value, 'published_at' => null]);
        Workspace::factory()->pending()->create(['published_at' => now()]);

        $this->assertSame(1, $this->service()->discover([])->total());
    }

    public function test_discover_filters_by_price_range(): void
    {
        $this->publicWorkspace(['price_per_month' => 20]);
        $this->publicWorkspace(['price_per_month' => 60]);

        $result = $this->service()->discover(['min_price' => 30, 'max_price' => 100]);

        $this->assertSame(1, $result->total());
        $this->assertEquals(60, (float) $result->items()[0]->price_per_month);
    }

    public function test_discover_filters_by_amenities_with_and_semantics(): void
    {
        $this->publicWorkspace(['amenities' => ['wifi', 'coffee']]);
        $this->publicWorkspace(['amenities' => ['wifi']]);

        $result = $this->service()->discover(['amenities' => ['wifi', 'coffee']]);

        $this->assertSame(1, $result->total());
    }

    public function test_discover_filters_by_min_rating(): void
    {
        $this->publicWorkspace(['avg_rating' => 4.5]);
        $this->publicWorkspace(['avg_rating' => 2.0]);

        $this->assertSame(1, $this->service()->discover(['min_rating' => 4])->total());
    }

    public function test_discover_searches_name_and_description(): void
    {
        $this->publicWorkspace(['name' => 'Quiet Corner', 'description' => 'plain']);
        $this->publicWorkspace(['name' => 'Loud Place', 'description' => 'has a quiet room']);
        $this->publicWorkspace(['name' => 'Nothing', 'description' => 'nope']);

        $this->assertSame(2, $this->service()->discover(['search' => 'quiet'])->total());
    }

    public function test_discover_sorts_by_price_ascending(): void
    {
        $this->publicWorkspace(['price_per_month' => 90]);
        $this->publicWorkspace(['price_per_month' => 10]);

        $result = $this->service()->discover(['sort' => 'price_asc']);

        $this->assertEquals(10, (float) $result->items()[0]->price_per_month);
    }

    public function test_discover_sorts_by_rating_descending(): void
    {
        $this->publicWorkspace(['avg_rating' => 1.0]);
        $this->publicWorkspace(['avg_rating' => 5.0]);

        $result = $this->service()->discover(['sort' => 'rating_desc']);

        $this->assertEquals(5.0, (float) $result->items()[0]->avg_rating);
    }

    public function test_active_cities_lists_only_public_cities(): void
    {
        $gaza = City::create(['name_ar' => 'غزة', 'name_en' => 'Gaza', 'is_active' => true]);
        $this->publicWorkspace(['city' => $gaza->name_ar, 'city_id' => $gaza->id]);
        // active but unpublished -> excluded
        Workspace::factory()->create([
            'status' => WorkspaceStatus::Active->value,
            'published_at' => null,
            'city' => 'Hidden City',
        ]);

        $cities = $this->service()->activeCities();

        $this->assertContains('غزة', $cities);
        $this->assertNotContains('Hidden City', $cities);
    }

    public function test_find_active_public_returns_only_visible(): void
    {
        $visible = $this->publicWorkspace();
        $hidden = Workspace::factory()->create([
            'status' => WorkspaceStatus::Active->value,
            'published_at' => null,
        ]);

        $this->assertNotNull($this->service()->findActivePublic($visible->id));
        $this->assertNull($this->service()->findActivePublic($hidden->id));
    }
}
