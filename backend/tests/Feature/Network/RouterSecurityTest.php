<?php

declare(strict_types=1);

namespace Tests\Feature\Network;

use App\Enums\RouterCommandStatus;
use App\Enums\RouterCommandVerb;
use App\Enums\RouterStatus;
use App\Enums\WorkspaceFeature;
use App\Models\AuditLog;
use App\Models\Router;
use App\Models\RouterCommand;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Auth\TwoFactorService;
use App\Services\FeatureFlagService;
use App\Services\Router\RouterService;
use App\Support\Router\RouterKeyHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Task 2.7: serial-number lock, unlock and key rotation. */
class RouterSecurityTest extends TestCase
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
        $this->owner->forceFill(['two_factor_secret' => 'SECRET', 'two_factor_confirmed_at' => now()])->save();

        $workspace = Workspace::factory()->create(['code' => self::CODE, 'owner_id' => $this->owner->id]);
        app(FeatureFlagService::class)->update($workspace, [WorkspaceFeature::Network->value => true]);

        $this->key = RouterKeyHasher::generate();
        $this->router = Router::factory()->withKey($this->key)->create(['workspace_id' => $workspace->id]);
    }

    /** The step-up marker is scoped to the access token, so it is set after signing in. */
    private function actAsOwner(bool $verified = true): void
    {
        Sanctum::actingAs($this->owner);

        if ($verified) {
            app(TwoFactorService::class)->markVerified($this->owner);
        }
    }

    private function sync(string $serial, ?string $key = null, array $acks = [])
    {
        $this->batch++;
        $body = "H|batch{$this->batch}|{$this->batch}|boot7|2026-11-03 12:30:00|full|nonce{$this->batch}|1.0.0|{$serial}|7.14.3\n";
        $body .= implode('', array_map(static fn (string $ack): string => "A|{$ack}\n", $acks));

        return $this->call('POST', '/api/v1/hotspot/'.self::CODE.'/sync', [], [], [], [
            'CONTENT_TYPE' => 'text/plain',
            'HTTP_X-API-Key' => $key ?? $this->key,
            'HTTP_ACCEPT' => 'text/plain',
        ], $body);
    }

    private function setKeyCommand(): RouterCommand
    {
        return RouterCommand::query()->withoutWorkspaceScope()
            ->where('router_id', $this->router->id)->where('verb', RouterCommandVerb::SetKey->value)->firstOrFail();
    }

    public function test_the_first_sync_binds_the_serial_number(): void
    {
        $this->sync('SN-1')->assertOk();

        $this->assertSame('SN-1', $this->router->fresh()->serial_number);
        $this->assertDatabaseHas('audit_logs', ['action' => 'network.router.serial_bound']);

        $this->sync('SN-1')->assertOk();
    }

    public function test_a_valid_key_from_another_serial_is_rejected_and_locks_the_router(): void
    {
        $this->sync('SN-1')->assertOk();

        $this->sync('SN-CLONE')->assertForbidden();

        $this->assertSame(RouterStatus::Locked, $this->router->fresh()->status);
        $this->assertSame('SN-1', $this->router->fresh()->serial_number);
        $this->assertDatabaseHas('audit_logs', ['action' => 'network.router.locked']);
        // The real device is shut out as well until the owner unlocks it.
        $this->sync('SN-1')->assertForbidden();
    }

    public function test_a_sync_without_a_serial_is_rejected_once_the_serial_is_bound(): void
    {
        $this->sync('SN-1')->assertOk();

        $this->sync('')->assertForbidden();

        $this->assertSame(RouterStatus::Locked, $this->router->fresh()->status);
        $this->assertSame('SN-1', $this->router->fresh()->serial_number);
        $this->assertDatabaseHas('audit_logs', ['action' => 'network.router.locked']);
    }

    public function test_unlock_lets_a_new_serial_bind(): void
    {
        $this->sync('SN-1');
        $this->sync('SN-CLONE')->assertForbidden();
        $this->actAsOwner();

        $this->postJson("/api/workspace/routers/{$this->router->id}/unlock")->assertOk();

        $this->assertNull($this->router->fresh()->serial_number);
        $this->sync('SN-2')->assertOk();
        $fresh = $this->router->fresh();
        $this->assertSame('SN-2', $fresh->serial_number);
        $this->assertSame(RouterStatus::Online, $fresh->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'network.router.unlocked']);
    }

    public function test_rotation_queues_set_key_and_returns_the_new_key_once(): void
    {
        $this->actAsOwner();

        $response = $this->postJson("/api/workspace/routers/{$this->router->id}/rotate-key")->assertOk();

        $newKey = $response->json('data.key');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32,96}$/', $newKey);
        $this->assertSame($newKey, $this->setKeyCommand()->payload['key']);
        $this->assertSame($this->router->id, Router::findByKey($newKey)?->id);
        $this->assertSame($this->router->id, Router::findByKey($this->key)?->id);

        $audit = AuditLog::query()->where('action', 'network.router.key_rotated')->firstOrFail();
        $this->assertStringNotContainsString($newKey, json_encode($audit->toArray()));
    }

    public function test_router_keeps_working_and_old_key_is_revoked_after_the_acknowledgement(): void
    {
        $this->actAsOwner();
        $newKey = $this->postJson("/api/workspace/routers/{$this->router->id}/rotate-key")->json('data.key');

        $reply = $this->sync('SN-1')->getContent();
        $this->assertStringContainsString('C|'.$this->setKeyCommand()->id.'|set-key|'.$newKey, $reply);

        // The router now speaks with the new key and confirms it.
        $this->sync('SN-1', $newKey, [$this->setKeyCommand()->id.'|ok'])->assertOk();

        $this->assertSame(RouterCommandStatus::Acknowledged, $this->setKeyCommand()->status);
        $this->assertNull($this->router->fresh()->previous_key_hash);
        $this->sync('SN-1', $this->key)->assertUnauthorized();
        $this->sync('SN-1', $newKey)->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'network.router.previous_key_revoked']);
    }

    public function test_previous_key_stops_working_when_the_grace_period_ends_unacknowledged(): void
    {
        $this->actAsOwner();
        $this->postJson("/api/workspace/routers/{$this->router->id}/rotate-key")->assertOk();

        $this->sync('SN-1')->assertOk();
        $this->travel(RouterService::KEY_GRACE_MINUTES + 1)->minutes();

        $this->sync('SN-1', $this->key)->assertUnauthorized();
    }

    public function test_a_second_rotation_is_refused_while_the_first_is_pending(): void
    {
        $this->actAsOwner();
        $this->postJson("/api/workspace/routers/{$this->router->id}/rotate-key")->assertOk();

        $this->postJson("/api/workspace/routers/{$this->router->id}/rotate-key")->assertStatus(409);
    }

    public function test_actions_need_a_recent_two_factor_check(): void
    {
        $this->actAsOwner(verified: false);

        $this->postJson("/api/workspace/routers/{$this->router->id}/unlock")
            ->assertForbidden()->assertJsonPath('code', 'two_factor_required');
        $this->postJson("/api/workspace/routers/{$this->router->id}/rotate-key")
            ->assertForbidden()->assertJsonPath('code', 'two_factor_required');
        $this->assertDatabaseMissing('router_commands', ['router_id' => $this->router->id]);
    }

    public function test_other_workspaces_routers_are_not_found_and_non_owners_are_refused(): void
    {
        $foreign = Router::factory()->create();
        $this->actAsOwner();
        $this->postJson("/api/workspace/routers/{$foreign->id}/unlock")->assertNotFound();
        $this->postJson("/api/workspace/routers/{$foreign->id}/rotate-key")->assertNotFound();

        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/workspace/routers/{$this->router->id}/unlock")->assertForbidden();
    }
}
