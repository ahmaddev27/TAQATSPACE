<?php

declare(strict_types=1);

namespace Tests\Feature\Workspaces;

use App\Enums\WorkspaceStatus;
use App\Models\City;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public city filter accepts the value in any form the explore page might
 * send — city id, Arabic name, or English name — and matches workspaces by it.
 */
class CityFilterTest extends TestCase
{
    use RefreshDatabase;

    private City $gaza;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gaza = City::create(['name_ar' => 'غزة', 'name_en' => 'Gaza', 'is_active' => true]);

        Workspace::factory()->create([
            'city_id' => $this->gaza->id,
            'city' => $this->gaza->name_ar,
            'status' => WorkspaceStatus::Active->value,
        ]);
    }

    public function test_filters_by_english_name(): void
    {
        $this->assertSame(1, Workspace::query()->inCity('Gaza')->count());
    }

    public function test_filters_by_arabic_name(): void
    {
        $this->assertSame(1, Workspace::query()->inCity('غزة')->count());
    }

    public function test_filters_by_city_id(): void
    {
        $this->assertSame(1, Workspace::query()->inCity($this->gaza->id)->count());
    }

    public function test_unknown_city_matches_nothing(): void
    {
        $this->assertSame(0, Workspace::query()->inCity('Atlantis')->count());
    }
}
