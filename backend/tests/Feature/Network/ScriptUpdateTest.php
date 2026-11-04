<?php

declare(strict_types=1);

namespace Tests\Feature\Network;

use App\Enums\RouterCommandStatus;
use App\Enums\RouterCommandVerb;
use App\Enums\WorkspaceFeature;
use App\Models\AuditLog;
use App\Models\Router;
use App\Models\RouterCommand;
use App\Models\User;
use App\Models\Workspace;
use App\Repositories\Eloquent\RouterScriptRepository;
use App\Services\FeatureFlagService;
use App\Support\Router\RouterKeyHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ScriptUpdateTest extends TestCase
{
    use RefreshDatabase;

    private const CODE = 'ABCD2345';

    private User $owner;

    private Router $router;

    private string $key;

    private int $batch = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);

        $this->owner = User::factory()->owner()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $this->owner->id, 'code' => self::CODE]);
        app(FeatureFlagService::class)->update($workspace, [WorkspaceFeature::Network->value => true]);

        $this->key = RouterKeyHasher::generate();
        $this->router = Router::factory()->withKey($this->key)->create([
            'workspace_id' => $workspace->id,
            'script_version' => '0.9.0',
            'mode' => 'full',
        ]);
    }

    private function url(): string
    {
        return "/api/workspace/routers/{$this->router->id}";
    }

    /** @return list<RouterCommand> */
    private function updateCommands(): array
    {
        return RouterCommand::query()->withoutWorkspaceScope()
            ->where('router_id', $this->router->id)
            ->where('verb', RouterCommandVerb::UpdateScript->value)
            ->get()->all();
    }

    private function syncBody(string $version, string $mode, array $acks = []): string
    {
        $this->batch++;
        $body = "H|b{$this->batch}|{$this->batch}|boot7|2026-11-03 12:30:00|{$mode}|n{$this->batch}|{$version}|SN1|7.14.3\n";

        return $body.implode('', array_map(static fn (string $ack): string => "A|{$ack}\n", $acks));
    }

    private function sync(string $body): string
    {
        return $this->call('POST', '/api/v1/hotspot/'.self::CODE.'/sync', [], [], [], [
            'CONTENT_TYPE' => 'text/plain',
            'HTTP_X-API-Key' => $this->key,
        ], $body)->assertOk()->getContent();
    }

    public function test_older_router_reports_an_update_and_nothing_is_pushed(): void
    {
        Sanctum::actingAs($this->owner);

        $this->getJson($this->url().'/script-update')
            ->assertOk()
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.current_version', '0.9.0')
            ->assertJsonPath('data.latest_version', app(RouterScriptRepository::class)->version());

        $this->assertSame([], $this->updateCommands());
    }

    public function test_confirmation_queues_one_command_per_script_with_the_rendered_sha(): void
    {
        Sanctum::actingAs($this->owner);

        $this->postJson($this->url().'/update-script')->assertStatus(202)->assertJsonPath('data.queued', 4);

        $scripts = app(RouterScriptRepository::class);
        $commands = $this->updateCommands();
        $bySystemName = collect($commands)->mapWithKeys(fn (RouterCommand $c): array => [$c->payload['script'] => $c->payload['sha256']]);

        $this->assertSame(['cws-hooks', 'cws-interpreter', 'cws-snapshot', 'cws-sync'], $bySystemName->keys()->sort()->values()->all());
        $this->assertSame($scripts->renderedSha($this->router, 'sync.rsc'), $bySystemName['cws-sync']);
        $this->assertNotSame($scripts->sha256('sync.rsc'), $bySystemName['cws-sync'], 'The rendered sha, not the template hash, is sent.');
        $this->assertDatabaseHas('audit_logs', ['action' => 'network.router.script_update_pushed']);
        $this->assertSame(1, AuditLog::query()->where('action', 'network.router.script_update_pushed')->count());
    }

    public function test_up_to_date_router_is_not_pushed_unless_a_full_update_is_requested(): void
    {
        $this->router->update(['script_version' => app(RouterScriptRepository::class)->version()]);
        Sanctum::actingAs($this->owner);

        $this->getJson($this->url().'/script-update')->assertJsonPath('data.available', false);
        $this->postJson($this->url().'/update-script')->assertUnprocessable();
        $this->assertSame([], $this->updateCommands());

        $this->postJson($this->url().'/update-script', ['full' => true])->assertStatus(202)->assertJsonPath('data.queued', 4);
    }

    public function test_repeated_confirmation_does_not_duplicate_open_commands(): void
    {
        Sanctum::actingAs($this->owner);

        $this->postJson($this->url().'/update-script')->assertStatus(202);
        $this->postJson($this->url().'/update-script')->assertStatus(202)->assertJsonPath('data.queued', 0);

        $this->assertCount(4, $this->updateCommands());
    }

    public function test_command_is_delivered_and_acknowledged_through_the_sync(): void
    {
        Sanctum::actingAs($this->owner);
        $this->postJson($this->url().'/update-script')->assertStatus(202);

        $reply = $this->sync($this->syncBody('0.9.0', 'full'));
        $this->assertStringContainsString('|update-script|cws-', $reply);

        $ids = collect($this->updateCommands())->map->getKey()->all();
        $this->sync($this->syncBody('1.0.0', 'full', array_map(fn (string $id): string => "{$id}|ok", $ids)));

        $this->assertSame([RouterCommandStatus::Acknowledged], collect($this->updateCommands())->map->status->unique()->values()->all());
        $this->assertSame('1.0.0', $this->router->fresh()->script_version);
    }

    public function test_sha_mismatch_rejection_is_recorded_as_a_failure(): void
    {
        Sanctum::actingAs($this->owner);
        $this->postJson($this->url().'/update-script')->assertStatus(202);
        $this->sync($this->syncBody('0.9.0', 'full'));

        $id = $this->updateCommands()[0]->getKey();
        $this->sync($this->syncBody('0.9.0', 'full', ["{$id}|sha-mismatch"]));

        $command = RouterCommand::query()->withoutWorkspaceScope()->findOrFail($id);
        $this->assertSame(RouterCommandStatus::Failed, $command->status);
        $this->assertSame('sha-mismatch', $command->error);
    }

    public function test_poll_only_router_keeps_its_mode_in_the_pushed_script(): void
    {
        $this->sync($this->syncBody('0.9.0', 'poll-only'));
        $this->assertSame('poll-only', $this->router->fresh()->mode);

        Sanctum::actingAs($this->owner);
        $this->postJson($this->url().'/update-script')->assertStatus(202);

        $hooks = collect($this->updateCommands())->first(fn (RouterCommand $c): bool => $c->payload['script'] === 'cws-hooks');
        $body = $this->withHeader('X-API-Key', $this->key)->get('/api/v1/hotspot/'.self::CODE.'/scripts/cws-hooks')->assertOk()->getContent();

        $this->assertStringContainsString(':global cwsMode "poll-only"', $body);
        $this->assertSame($hooks->payload['sha256'], hash('sha256', $body), 'The sha sent equals the hash of what the router downloads.');
    }

    public function test_script_download_requires_the_router_key(): void
    {
        $this->get('/api/v1/hotspot/'.self::CODE.'/scripts/hooks')->assertUnauthorized();
        $this->withHeader('X-API-Key', RouterKeyHasher::generate())->get('/api/v1/hotspot/'.self::CODE.'/scripts/hooks')->assertUnauthorized();
    }

    public function test_only_the_owner_of_the_router_can_use_the_endpoints(): void
    {
        $other = User::factory()->owner()->create();
        $foreign = Workspace::factory()->create(['owner_id' => $other->id]);
        app(FeatureFlagService::class)->update($foreign, [WorkspaceFeature::Network->value => true]);
        Sanctum::actingAs($other);

        $this->postJson($this->url().'/update-script')->assertNotFound();
        $this->getJson($this->url().'/script-update')->assertNotFound();
        $this->assertSame([], $this->updateCommands());
    }

    public function test_guests_and_members_are_rejected(): void
    {
        $this->postJson($this->url().'/update-script')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->postJson($this->url().'/update-script')->assertForbidden();
    }
}
