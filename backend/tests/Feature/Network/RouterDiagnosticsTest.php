<?php

declare(strict_types=1);

namespace Tests\Feature\Network;

use App\Enums\RouterCommandStatus;
use App\Enums\RouterStatus;
use App\Enums\WorkspaceFeature;
use App\Models\HotspotEvent;
use App\Models\Router;
use App\Models\RouterApiLog;
use App\Models\RouterCommand;
use App\Models\User;
use App\Models\Workspace;
use App\Repositories\Eloquent\RouterScriptRepository;
use App\Services\FeatureFlagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Task 2.10: the owner's "API diagnostics" endpoints (request log and deep check). */
class RouterDiagnosticsTest extends TestCase
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
        $this->router = Router::factory()->create([
            'workspace_id' => $this->workspace->id,
            'status' => RouterStatus::Online,
            'last_seen_at' => now()->subMinute(),
            'clock_offset_seconds' => 1,
            'serial_number' => 'SN1',
            'script_version' => app(RouterScriptRepository::class)->version(),
        ]);
        Sanctum::actingAs($this->owner);
    }

    /** @return array<string, array{name: string, status: string, detail: string}> */
    private function diagnose(?Router $router = null): array
    {
        $response = $this->postJson('/api/workspace/routers/'.($router ?? $this->router)->id.'/diagnose')->assertOk();

        return collect($response->json('data.checks'))->keyBy('name')->all();
    }

    public function test_logs_are_listed_newest_first_with_meta_and_no_secrets(): void
    {
        RouterApiLog::factory()->count(3)->create(['router_id' => $this->router->id]);
        $newest = RouterApiLog::factory()->create(['router_id' => $this->router->id, 'path' => '/api/v1/hotspot/X/sync']);

        $response = $this->getJson("/api/workspace/routers/{$this->router->id}/logs?per_page=2")->assertOk();

        $response->assertJsonCount(2, 'data.logs')
            ->assertJsonPath('data.logs.0.id', $newest->id)
            ->assertJsonPath('data.meta.total', 4)
            ->assertJsonPath('data.meta.last_page', 2);
        $this->assertArrayNotHasKey('request_id', $response->json('data.logs.0'));
        $this->assertStringNotContainsString('key_hash', $response->getContent());
    }

    public function test_logs_filter_by_code_error_and_date_range(): void
    {
        RouterApiLog::factory()->create(['router_id' => $this->router->id, 'response_code' => 200]);
        $failed = RouterApiLog::factory()->create(['router_id' => $this->router->id, 'response_code' => 422, 'error_message' => 'Bad batch id']);
        $old = RouterApiLog::factory()->create(['router_id' => $this->router->id, 'response_code' => 500, 'error_message' => 'Boom']);
        DB::table('router_api_logs')->where('id', $old->id)->update(['created_at' => now()->subDays(5)]);
        $base = "/api/workspace/routers/{$this->router->id}/logs";

        $this->getJson("$base?response_code=422")->assertJsonCount(1, 'data.logs')->assertJsonPath('data.logs.0.id', $failed->id);
        $this->getJson("$base?error=batch")->assertJsonCount(1, 'data.logs')->assertJsonPath('data.logs.0.id', $failed->id);
        $this->getJson("$base?error=%25")->assertJsonCount(0, 'data.logs');
        $this->getJson("$base?from=".now()->subDays(6)->toDateString().'&to='.now()->subDays(4)->toDateString())
            ->assertJsonCount(1, 'data.logs')->assertJsonPath('data.logs.0.id', $old->id);
    }

    public function test_logs_reject_invalid_filters(): void
    {
        $this->getJson("/api/workspace/routers/{$this->router->id}/logs?response_code=abc&to=2026-01-01&from=2026-02-01")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['response_code', 'to']);
    }

    public function test_another_workspace_router_is_not_found(): void
    {
        $foreign = Router::factory()->create();
        RouterApiLog::factory()->create(['router_id' => $foreign->id]);

        $this->getJson("/api/workspace/routers/{$foreign->id}/logs")->assertNotFound();
        $this->postJson("/api/workspace/routers/{$foreign->id}/diagnose")->assertNotFound();
    }

    public function test_non_owner_and_missing_feature_are_refused(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/workspace/routers/{$this->router->id}/logs")->assertForbidden();

        Sanctum::actingAs($this->owner);
        app(FeatureFlagService::class)->update($this->workspace, [WorkspaceFeature::Network->value => false]);
        $this->postJson("/api/workspace/routers/{$this->router->id}/diagnose")->assertForbidden();
    }

    public function test_a_healthy_router_passes_every_check(): void
    {
        $checks = $this->diagnose();

        $this->assertSame(['last_sync', 'clock_drift', 'seq_gaps', 'stuck_commands', 'script_version', 'serial'], array_keys($checks));
        foreach ($checks as $check) {
            $this->assertSame('pass', $check['status'], $check['name'].': '.$check['detail']);
        }
    }

    public function test_a_router_that_never_synced_fails_the_sync_check_with_a_reason(): void
    {
        $router = Router::factory()->create(['workspace_id' => $this->workspace->id, 'last_seen_at' => null, 'serial_number' => null]);

        $checks = $this->diagnose($router);

        $this->assertSame('fail', $checks['last_sync']['status']);
        $this->assertSame('The router has never synced with the platform.', $checks['last_sync']['detail']);
        $this->assertSame('warning', $checks['serial']['status']);
    }

    public function test_sync_age_and_clock_drift_thresholds(): void
    {
        $this->router->update(['last_seen_at' => now()->subMinutes(5), 'clock_offset_seconds' => -120]);
        $checks = $this->diagnose();
        $this->assertSame('warning', $checks['last_sync']['status']);
        $this->assertSame('warning', $checks['clock_drift']['status']);

        $this->travel(11)->minutes();
        $this->router->update(['clock_offset_seconds' => 900]);
        $checks = $this->diagnose();
        $this->assertSame('fail', $checks['last_sync']['status']);
        $this->assertSame('fail', $checks['clock_drift']['status']);
    }

    public function test_sequence_gaps_in_the_latest_boot_are_reported(): void
    {
        foreach ([1, 2, 5, 6] as $seq) {
            HotspotEvent::query()->create([
                'router_id' => $this->router->id,
                'workspace_id' => $this->workspace->id,
                'event_uid' => "boot2-{$seq}",
                'batch_id' => 'b',
                'type' => 'login',
                'occurred_at' => now(),
            ]);
        }

        $check = $this->diagnose()['seq_gaps'];

        $this->assertSame('warning', $check['status']);
        $this->assertStringContainsString('2 events', $check['detail']);
    }

    public function test_old_open_commands_are_stuck_and_very_old_ones_fail(): void
    {
        $command = RouterCommand::factory()->create(['router_id' => $this->router->id, 'status' => RouterCommandStatus::Delivered]);
        RouterCommand::factory()->create(['router_id' => $this->router->id, 'status' => RouterCommandStatus::Acknowledged]);
        DB::table('router_commands')->where('id', $command->id)->update(['created_at' => now()->subMinutes(20)]);

        $this->assertSame('warning', $this->diagnose()['stuck_commands']['status']);

        $this->travel(2)->minutes();
        DB::table('router_commands')->where('id', $command->id)->update(['created_at' => now()->subHours(2)]);
        $this->travel(20)->seconds();

        $this->assertSame('fail', $this->diagnose()['stuck_commands']['status']);
    }

    public function test_outdated_script_and_locked_router_are_flagged(): void
    {
        $this->router->update(['script_version' => '0.0.1', 'status' => RouterStatus::Locked]);

        $checks = $this->diagnose();

        $this->assertSame('warning', $checks['script_version']['status']);
        $this->assertSame('fail', $checks['serial']['status']);
    }

    public function test_the_result_is_cached_for_fifteen_seconds(): void
    {
        $this->assertSame('pass', $this->diagnose()['serial']['status']);

        $this->router->update(['status' => RouterStatus::Locked]);
        $this->assertSame('pass', $this->diagnose()['serial']['status']);

        $this->travel(16)->seconds();
        $this->assertSame('fail', $this->diagnose()['serial']['status']);
    }
}
