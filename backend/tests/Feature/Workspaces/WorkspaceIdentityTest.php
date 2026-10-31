<?php

declare(strict_types=1);

namespace Tests\Feature\Workspaces;

use App\Enums\PlatformPlan;
use App\Enums\PlatformStatus;
use App\Models\User;
use App\Models\Workspace;
use App\Services\WorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\TestCase;

class WorkspaceIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_workspace_assigns_unique_slug_and_code(): void
    {
        $service = app(WorkspaceService::class);
        $attributes = ['name' => 'Twin', 'address' => 'x', 'city' => 'Gaza'];
        $a = $service->createForOwner((string) User::factory()->owner()->create()->id, $attributes);
        $b = $service->createForOwner((string) User::factory()->owner()->create()->id, $attributes);

        $this->assertSame('twin', $a->slug);
        $this->assertSame('twin-2', $b->slug);
        $this->assertNotSame($a->code, $b->code);
        $this->assertSame(PlatformPlan::Standard, $a->fresh()->platform_plan);
        $this->assertSame(PlatformStatus::Active, $a->fresh()->platform_status);
    }

    public function test_slug_cannot_be_changed_once_assigned(): void
    {
        $workspace = Workspace::factory()->create();

        $this->expectException(LogicException::class);
        $workspace->update(['slug' => 'hijacked']);
    }

    public function test_code_cannot_be_changed_once_assigned(): void
    {
        $workspace = Workspace::factory()->create();

        $this->expectException(LogicException::class);
        $workspace->update(['code' => 'ZZZZZZZZ']);
    }

    public function test_other_attributes_remain_editable(): void
    {
        $workspace = Workspace::factory()->create();
        $workspace->update(['name' => 'Renamed']);

        $this->assertSame('Renamed', $workspace->fresh()->name);
    }

    public function test_settings_endpoint_ignores_identity_fields(): void
    {
        $owner = User::factory()->owner()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        Sanctum::actingAs($owner);

        $this->putJson('/api/workspace/settings', ['name' => 'New', 'slug' => 'evil', 'code' => 'AAAAAAAA'])
            ->assertOk()
            ->assertJsonPath('data.slug', $workspace->slug)
            ->assertJsonPath('data.code', $workspace->code);
    }

    public function test_owner_sees_identity_but_public_does_not(): void
    {
        $owner = User::factory()->owner()->create();
        $workspace = Workspace::factory()->create([
            'owner_id' => $owner->id, 'status' => 'active', 'published_at' => now(),
        ]);

        $this->getJson('/api/workspaces/'.$workspace->id)
            ->assertOk()
            ->assertJsonMissingPath('data.slug')
            ->assertJsonMissingPath('data.code');

        Sanctum::actingAs($owner);
        $this->getJson('/api/workspaces/'.$workspace->id)
            ->assertOk()
            ->assertJsonPath('data.slug', $workspace->slug)
            ->assertJsonPath('data.platform_plan', 'standard');
    }

    public function test_backfill_assigns_missing_identity_and_is_idempotent(): void
    {
        Workspace::factory()->count(3)->create(['name' => 'Legacy']);
        DB::table('workspaces')->update(['slug' => null, 'code' => null]);

        $this->artisan('workspaces:backfill-identity')->assertSuccessful();

        $rows = Workspace::query()->get();
        $this->assertCount(3, $rows->pluck('slug')->filter()->unique());
        $this->assertCount(3, $rows->pluck('code')->filter()->unique());

        $before = $rows->pluck('code', 'id')->all();
        $this->artisan('workspaces:backfill-identity')->assertSuccessful();

        $this->assertSame($before, Workspace::query()->pluck('code', 'id')->all());
    }
}
