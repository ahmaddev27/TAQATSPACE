<?php

declare(strict_types=1);

namespace Tests\Feature\Network;

use App\Enums\GapReason;
use App\Enums\WorkspaceFeature;
use App\Models\Router;
use App\Models\RouterGap;
use App\Models\User;
use App\Models\Workspace;
use App\Services\FeatureFlagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Task 3.6: GET /api/workspace/network/gaps. */
class RouterGapEndpointTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Workspace $workspace;

    private Router $router;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->owner()->create();
        $this->workspace = Workspace::factory()->create(['owner_id' => $this->owner->id]);
        app(FeatureFlagService::class)->update($this->workspace, [WorkspaceFeature::Network->value => true]);
        $this->router = Router::factory()->create(['workspace_id' => $this->workspace->id]);
    }

    private function gap(Router $router, string $start, ?string $end, GapReason $reason = GapReason::NoSync): RouterGap
    {
        return RouterGap::query()->withoutWorkspaceScope()->create([
            'router_id' => $router->id,
            'workspace_id' => $router->workspace_id,
            'started_at' => $start,
            'ended_at' => $end,
            'reason' => $reason,
            'resolved_at' => $end,
        ]);
    }

    public function test_the_owner_lists_gaps_newest_first_with_pagination(): void
    {
        $this->gap($this->router, '2026-11-01 10:00:00', '2026-11-01 10:20:00');
        $this->gap($this->router, '2026-11-03 10:00:00', null);
        $this->gap($this->router, '2026-11-02 10:00:00', '2026-11-02 10:07:00', GapReason::Reboot);
        Sanctum::actingAs($this->owner);

        $response = $this->getJson('/api/workspace/network/gaps?per_page=2')->assertOk();

        $this->assertSame(3, $response->json('data.meta.total'));
        $this->assertSame(2, $response->json('data.meta.per_page'));
        $this->assertCount(2, $response->json('data.gaps'));
        $this->assertTrue($response->json('data.gaps.0.is_open'));
        $this->assertSame('reboot', $response->json('data.gaps.1.reason'));
    }

    public function test_filters_narrow_the_list(): void
    {
        $other = Router::factory()->create(['workspace_id' => $this->workspace->id]);
        $this->gap($this->router, '2026-11-01 10:00:00', '2026-11-01 10:20:00');
        $this->gap($other, '2026-11-03 10:00:00', null);
        Sanctum::actingAs($this->owner);

        $this->assertSame(1, $this->getJson('/api/workspace/network/gaps?open=1')->json('data.meta.total'));
        $this->assertSame(1, $this->getJson('/api/workspace/network/gaps?open=0')->json('data.meta.total'));
        $this->assertSame(1, $this->getJson("/api/workspace/network/gaps?router_id={$other->id}")->json('data.meta.total'));
        $this->assertSame(1, $this->getJson('/api/workspace/network/gaps?from=2026-11-02&to=2026-11-04')->json('data.meta.total'));
    }

    public function test_the_range_filter_includes_gaps_that_began_before_it_and_overlap_it(): void
    {
        $this->gap($this->router, '2026-10-30 22:00:00', '2026-11-02 08:00:00');
        $this->gap($this->router, '2026-10-20 10:00:00', '2026-10-21 10:00:00');
        $this->gap($this->router, '2026-10-31 10:00:00', null);
        $this->gap($this->router, '2026-11-09 10:00:00', null);
        Sanctum::actingAs($this->owner);

        $ids = collect($this->getJson('/api/workspace/network/gaps?from=2026-11-02&to=2026-11-04')->assertOk()->json('data.gaps'));

        $this->assertSame(['2026-10-31 10:00:00', '2026-10-30 22:00:00'], $ids->pluck('started_at')->map(fn (string $at) => \Illuminate\Support\Carbon::parse($at)->format('Y-m-d H:i:s'))->all());
    }

    public function test_gaps_of_another_workspace_are_invisible_and_cannot_be_filtered_by(): void
    {
        $otherOwner = User::factory()->owner()->create();
        $otherWorkspace = Workspace::factory()->create(['owner_id' => $otherOwner->id]);
        $foreign = Router::factory()->create(['workspace_id' => $otherWorkspace->id]);
        $this->gap($foreign, '2026-11-01 10:00:00', null);
        Sanctum::actingAs($this->owner);

        $this->assertSame(0, $this->getJson('/api/workspace/network/gaps')->assertOk()->json('data.meta.total'));
        $this->getJson("/api/workspace/network/gaps?router_id={$foreign->id}")->assertStatus(422);
    }

    public function test_a_member_cannot_read_gaps(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/workspace/network/gaps')->assertForbidden();
    }

    public function test_the_endpoint_is_closed_while_the_network_module_is_off(): void
    {
        app(FeatureFlagService::class)->update($this->workspace, [WorkspaceFeature::Network->value => false]);
        Sanctum::actingAs($this->owner);

        $this->assertContains($this->getJson('/api/workspace/network/gaps')->status(), [403, 404]);
    }
}
