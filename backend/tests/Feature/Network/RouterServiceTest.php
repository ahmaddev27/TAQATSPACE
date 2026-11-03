<?php

declare(strict_types=1);

namespace Tests\Feature\Network;

use App\Enums\RouterStatus;
use App\Models\Router;
use App\Models\Workspace;
use App\Services\Router\RouterService;
use App\Support\Router\RouterKeyHasher;
use App\Support\Tenancy\CurrentWorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouterServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): RouterService
    {
        return app(RouterService::class);
    }

    public function test_create_returns_the_plain_key_once_and_stores_only_the_hash(): void
    {
        $workspace = Workspace::factory()->create();

        $created = $this->service()->create($workspace, 'Lobby router');

        $stored = Router::withoutGlobalScopes()->findOrFail($created->router->id);
        $this->assertSame(RouterKeyHasher::hash($created->plainKey), $stored->key_hash);
        $this->assertNotSame($created->plainKey, $stored->key_hash);
        $this->assertSame(RouterStatus::Pending, $stored->status);
        $this->assertNull($stored->serial_number);
        $this->assertArrayNotHasKey('key_hash', $stored->toArray());
        $this->assertDatabaseMissing('routers', ['key_hash' => $created->plainKey]);
    }

    public function test_previous_key_works_during_the_grace_period_then_stops(): void
    {
        $created = $this->service()->create(Workspace::factory()->create(), 'R1');
        $old = $created->plainKey;

        $new = $this->service()->rotateKeyHash($created->router)->plainKey;

        $this->assertSame($created->router->id, Router::findByKey($new)?->id);
        $this->assertSame($created->router->id, Router::findByKey($old)?->id);

        $this->travel(RouterService::KEY_GRACE_MINUTES + 1)->minutes();

        $this->assertNull(Router::findByKey($old));
        $this->assertSame($created->router->id, Router::findByKey($new)?->id);
    }

    public function test_unknown_key_resolves_nothing(): void
    {
        $this->service()->create(Workspace::factory()->create(), 'R1');

        $this->assertNull(Router::findByKey('nope'));
    }

    public function test_routers_are_isolated_per_workspace(): void
    {
        $a = Workspace::factory()->create();
        $b = Workspace::factory()->create();
        $this->service()->create($a, 'A router');
        $this->service()->create($b, 'B router');

        app(CurrentWorkspaceContext::class)->set($a);

        $this->assertSame(['A router'], Router::query()->pluck('name')->all());
    }

    public function test_disable_and_delete(): void
    {
        $created = $this->service()->create(Workspace::factory()->create(), 'R1');

        $this->service()->disable($created->router);
        $this->assertSame(RouterStatus::Disabled, $created->router->fresh()->status);

        $this->service()->delete($created->router);
        $this->assertDatabaseMissing('routers', ['id' => $created->router->id]);
    }

    public function test_sensitive_actions_are_audited(): void
    {
        $created = $this->service()->create(Workspace::factory()->create(), 'R1');
        $this->service()->rotateKeyHash($created->router);

        $this->assertDatabaseHas('audit_logs', ['action' => 'network.router.created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'network.router.key_rotated']);
    }
}
