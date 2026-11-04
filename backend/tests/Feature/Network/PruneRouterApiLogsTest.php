<?php

declare(strict_types=1);

namespace Tests\Feature\Network;

use App\Models\Router;
use App\Models\RouterApiLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class PruneRouterApiLogsTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletes_only_logs_older_than_14_days(): void
    {
        $router = Router::factory()->create();
        $old = RouterApiLog::factory()->create(['router_id' => $router->id, 'created_at' => now()->subDays(15)]);
        $edge = RouterApiLog::factory()->create(['router_id' => $router->id, 'created_at' => now()->subDays(13)]);
        $fresh = RouterApiLog::factory()->create(['router_id' => $router->id]);

        $this->artisan('router-logs:prune')->assertSuccessful();

        $this->assertDatabaseMissing('router_api_logs', ['id' => $old->id]);
        $this->assertDatabaseHas('router_api_logs', ['id' => $edge->id]);
        $this->assertDatabaseHas('router_api_logs', ['id' => $fresh->id]);
    }

    public function test_command_is_scheduled(): void
    {
        $commands = collect(app(Schedule::class)->events())->pluck('command')->implode(' ');

        $this->assertStringContainsString('router-logs:prune', $commands);
    }
}
