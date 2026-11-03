<?php

declare(strict_types=1);

namespace Tests\Feature\Network;

use App\Enums\WorkspaceFeature;
use App\Models\AuditLog;
use App\Models\Router;
use App\Models\RouterInstallToken;
use App\Models\User;
use App\Models\Workspace;
use App\Services\FeatureFlagService;
use App\Services\Router\IssuedInstallToken;
use App\Services\Router\RouterInstallerService;
use App\Support\Router\RouterKeyHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Task 2.2: one-time install.rsc generator. */
class RouterInstallerTest extends TestCase
{
    use RefreshDatabase;

    private function installer(): RouterInstallerService
    {
        return app(RouterInstallerService::class);
    }

    private function router(array $attributes = []): Router
    {
        return Router::factory()->create($attributes + ['workspace_id' => Workspace::factory()->create(['code' => 'WS-ABC123'])->id]);
    }

    private function tokenOf(IssuedInstallToken $issued): string
    {
        return basename(parse_url($issued->url, PHP_URL_PATH), '.rsc');
    }

    private function fetch(IssuedInstallToken $issued)
    {
        return $this->get('/api/v1/hotspot/install/'.$this->tokenOf($issued).'.rsc');
    }

    public function test_script_is_served_as_plain_text_and_only_once(): void
    {
        $issued = $this->installer()->issueToken($this->router());

        $first = $this->fetch($issued);
        $first->assertOk();
        $this->assertStringStartsWith('text/plain', $first->headers->get('Content-Type'));
        $first->assertSee('check-certificate=yes', false);
        $first->assertSee('https://sync.taqat.space', false);
        $first->assertSee('WS-ABC123', false);

        $this->fetch($issued)->assertStatus(410);
    }

    public function test_script_contains_the_router_key_that_authenticates(): void
    {
        $router = $this->router();
        $body = $this->fetch($this->installer()->issueToken($router))->getContent();

        preg_match('/:global cwsKey "([0-9a-f]+)"/', $body, $match);
        $this->assertNotEmpty($match);
        $this->assertSame($router->id, Router::findByKey($match[1])?->id);
    }

    public function test_expired_token_returns_410(): void
    {
        $issued = $this->installer()->issueToken($this->router());

        $this->travel(31)->minutes();

        $this->fetch($issued)->assertStatus(410);
    }

    public function test_token_is_still_valid_just_before_expiry(): void
    {
        $issued = $this->installer()->issueToken($this->router());

        $this->travel(29)->minutes();

        $this->fetch($issued)->assertOk();
    }

    public function test_unknown_token_returns_404(): void
    {
        $this->get('/api/v1/hotspot/install/'.str_repeat('a', 48).'.rsc')->assertNotFound();
    }

    public function test_new_token_invalidates_the_previous_one(): void
    {
        $router = $this->router();
        $old = $this->installer()->issueToken($router);
        $new = $this->installer()->issueToken($router);

        $this->fetch($old)->assertStatus(410);
        $this->fetch($new)->assertOk();
    }

    public function test_only_hashes_and_encrypted_key_are_stored_and_key_is_cleared_after_use(): void
    {
        $issued = $this->installer()->issueToken($this->router());
        $plain = $this->tokenOf($issued);

        $row = RouterInstallToken::query()->firstOrFail();
        $this->assertSame(RouterKeyHasher::hash($plain), $row->token_hash);
        $this->assertNotNull($row->encrypted_key);

        $this->fetch($issued)->assertOk();

        $row->refresh();
        $this->assertNull($row->encrypted_key);
        $this->assertNotNull($row->used_at);
    }

    public function test_user_controlled_values_are_escaped(): void
    {
        $router = $this->router(['name' => "Lobby\" ; /system reset-configuration \$x\n/ip"]);

        $body = $this->fetch($this->installer()->issueToken($router))->getContent();

        $this->assertStringContainsString('"Lobby\" ; /system reset-configuration \$x /ip"', $body);
        $this->assertStringNotContainsString("\n/system reset", $body);
    }

    public function test_plain_key_never_reaches_logs_or_audit_trail(): void
    {
        Log::spy();
        $router = $this->router();
        $body = $this->fetch($this->installer()->issueToken($router))->getContent();
        preg_match('/:global cwsKey "([0-9a-f]+)"/', $body, $match);

        Log::shouldNotHaveReceived('error');
        $this->assertDatabaseCount('router_api_logs', 0);
        $this->assertStringNotContainsString($match[1], json_encode(AuditLog::query()->get()->toArray()));
        $this->assertStringNotContainsString($match[1], (string) @file_get_contents(storage_path('logs/laravel.log')));
    }

    public function test_script_matches_snapshot(): void
    {
        config(['services.router.sync_url' => 'https://sync.taqat.space']);
        $router = $this->router(['name' => 'Lobby']);
        $body = $this->fetch($this->installer()->issueToken($router))->getContent();
        $normalized = preg_replace('/(cwsKey ")[0-9a-f]+/', '$1KEY', $body);

        $this->assertSame(file_get_contents(__DIR__.'/snapshots/install.rsc'), $normalized);
    }

    public function test_sync_url_comes_from_config(): void
    {
        config(['services.router.sync_url' => 'https://sync.staging.example/']);

        $issued = $this->installer()->issueToken($this->router());

        $this->assertStringStartsWith('https://sync.staging.example/api/v1/hotspot/install/', $issued->url);
        $this->assertStringContainsString('check-certificate=yes', $issued->command);
        $this->assertStringContainsString('/import install.rsc', $issued->command);
    }

    public function test_owner_can_issue_a_token_for_own_router_only(): void
    {
        $owner = User::factory()->owner()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        app(FeatureFlagService::class)->update($workspace, [WorkspaceFeature::Network->value => true]);
        $own = Router::factory()->create(['workspace_id' => $workspace->id]);
        $foreign = $this->router();
        Sanctum::actingAs($owner);

        $this->postJson("/api/workspace/routers/{$own->id}/install-token")
            ->assertCreated()
            ->assertJsonPath('data.expires_at', fn ($value) => $value !== null)
            ->assertJsonStructure(['data' => ['command', 'url', 'expires_at']]);
        $this->postJson("/api/workspace/routers/{$foreign->id}/install-token")->assertNotFound();
    }

    public function test_issuing_requires_owner_and_network_flag(): void
    {
        $router = $this->router();
        $this->postJson("/api/workspace/routers/{$router->id}/install-token")->assertUnauthorized();

        $owner = User::factory()->owner()->create();
        Workspace::factory()->create(['owner_id' => $owner->id]);
        Sanctum::actingAs($owner);
        $this->postJson("/api/workspace/routers/{$router->id}/install-token")->assertForbidden();
    }
}
