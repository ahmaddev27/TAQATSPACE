<?php

declare(strict_types=1);

namespace Tests\Feature\Network;

use App\Enums\RouterCommandStatus;
use App\Enums\RouterCommandVerb;
use App\Enums\RouterStatus;
use App\Enums\WorkspaceFeature;
use App\Models\Router;
use App\Models\RouterCommand;
use App\Models\User;
use App\Models\Workspace;
use App\Services\FeatureFlagService;
use App\Services\Router\RouterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Task 2.9: the owner's "API & integration" page endpoints. */
class RouterControllerTest extends TestCase
{
    use RefreshDatabase;

    private const CODE = 'ABCD2345';

    private User $owner;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->owner()->create();
        $this->workspace = Workspace::factory()->create(['owner_id' => $this->owner->id, 'code' => self::CODE]);
        app(FeatureFlagService::class)->update($this->workspace, [WorkspaceFeature::Network->value => true]);
    }

    public function test_index_lists_the_workspace_routers_with_endpoint_and_code(): void
    {
        $router = Router::factory()->create(['workspace_id' => $this->workspace->id, 'serial_number' => 'SN1']);
        Router::factory()->create();

        Sanctum::actingAs($this->owner);
        $response = $this->getJson('/api/workspace/routers')->assertOk();

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $router->id)
            ->assertJsonPath('data.0.workspace_code', self::CODE)
            ->assertJsonPath('data.0.serial_number', 'SN1')
            ->assertJsonPath('data.0.endpoint', rtrim((string) config('services.router.sync_url'), '/').'/api/v1/hotspot/'.self::CODE.'/sync');
        $this->assertStringNotContainsString('key_hash', $response->getContent());
    }

    public function test_status_is_online_within_three_minutes_and_offline_after(): void
    {
        $router = Router::factory()->create([
            'workspace_id' => $this->workspace->id,
            'status' => RouterStatus::Online,
            'last_seen_at' => now()->subMinutes(2),
        ]);
        Sanctum::actingAs($this->owner);

        $this->getJson("/api/workspace/routers/{$router->id}")->assertJsonPath('data.status', 'online');

        $this->travel(2)->minutes();
        $this->getJson("/api/workspace/routers/{$router->id}")->assertJsonPath('data.status', 'offline');
    }

    public function test_locked_and_never_seen_routers_keep_their_stored_status(): void
    {
        $locked = Router::factory()->create([
            'workspace_id' => $this->workspace->id,
            'status' => RouterStatus::Locked,
            'last_seen_at' => now(),
        ]);
        $fresh = Router::factory()->create(['workspace_id' => $this->workspace->id]);
        Sanctum::actingAs($this->owner);

        $this->getJson("/api/workspace/routers/{$locked->id}")->assertJsonPath('data.status', 'locked');
        $this->getJson("/api/workspace/routers/{$fresh->id}")->assertJsonPath('data.status', 'pending');
    }

    public function test_show_returns_recent_commands_without_payloads_and_script_update_state(): void
    {
        $router = Router::factory()->create(['workspace_id' => $this->workspace->id, 'script_version' => '0.0.1']);
        RouterCommand::factory()->count(RouterService::RECENT_COMMANDS + 2)->create([
            'workspace_id' => $this->workspace->id,
            'router_id' => $router->id,
            'verb' => RouterCommandVerb::SetKey,
            'payload' => ['key' => str_repeat('a', 64)],
            'status' => RouterCommandStatus::Requested,
        ]);
        Sanctum::actingAs($this->owner);

        $response = $this->getJson("/api/workspace/routers/{$router->id}")->assertOk();

        $response->assertJsonCount(RouterService::RECENT_COMMANDS, 'data.commands')
            ->assertJsonPath('data.commands.0.verb', 'set-key')
            ->assertJsonPath('data.script_update.available', true);
        $this->assertStringNotContainsString(str_repeat('a', 64), $response->getContent());
    }

    public function test_store_creates_a_pending_router_without_returning_a_key(): void
    {
        Sanctum::actingAs($this->owner);

        $response = $this->postJson('/api/workspace/routers', ['name' => 'Front desk'])->assertCreated();

        $response->assertJsonPath('data.name', 'Front desk')->assertJsonPath('data.status', 'pending');
        $this->assertStringNotContainsString('"key"', $response->getContent());
        $this->assertDatabaseHas('routers', ['workspace_id' => $this->workspace->id, 'name' => 'Front desk']);
    }

    public function test_store_validates_the_name(): void
    {
        Sanctum::actingAs($this->owner);

        $this->postJson('/api/workspace/routers', [])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson('/api/workspace/routers', ['name' => str_repeat('x', 121)])->assertUnprocessable();
    }

    public function test_routers_of_other_workspaces_are_404(): void
    {
        $foreign = Router::factory()->create();
        Sanctum::actingAs($this->owner);

        $this->getJson("/api/workspace/routers/{$foreign->id}")->assertNotFound();
    }

    public function test_a_workspace_without_the_network_flag_is_blocked(): void
    {
        app(FeatureFlagService::class)->update($this->workspace, [WorkspaceFeature::Network->value => false]);
        Sanctum::actingAs($this->owner);

        $this->getJson('/api/workspace/routers')->assertForbidden();
        $this->postJson('/api/workspace/routers', ['name' => 'X'])->assertForbidden();
    }

    public function test_only_owners_may_use_the_endpoints(): void
    {
        $member = User::factory()->create();
        Sanctum::actingAs($member);

        $this->getJson('/api/workspace/routers')->assertForbidden();
        $this->postJson('/api/workspace/routers', ['name' => 'X'])->assertForbidden();
    }

    public function test_guests_are_rejected(): void
    {
        $this->getJson('/api/workspace/routers')->assertUnauthorized();
    }
}
