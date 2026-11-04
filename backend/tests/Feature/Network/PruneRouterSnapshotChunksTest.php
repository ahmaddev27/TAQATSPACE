<?php

declare(strict_types=1);

namespace Tests\Feature\Network;

use App\Models\Router;
use App\Models\RouterSnapshot;
use App\Models\RouterSnapshotChunk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneRouterSnapshotChunksTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletes_only_chunks_older_than_seven_days(): void
    {
        $router = Router::factory()->create();
        $snapshot = RouterSnapshot::query()->create([
            'router_id' => $router->id,
            'snapshot_id' => 's',
            'total_chunks' => 2,
            'taken_at' => now(),
        ]);
        $old = RouterSnapshotChunk::query()->create(['router_snapshot_id' => $snapshot->id, 'chunk_index' => 0, 'payload' => 'a']);
        $old->forceFill(['created_at' => now()->subDays(8)])->save();
        $fresh = RouterSnapshotChunk::query()->create(['router_snapshot_id' => $snapshot->id, 'chunk_index' => 1, 'payload' => 'b']);

        $this->artisan('router-snapshots:prune')->assertSuccessful();

        $this->assertDatabaseMissing('router_snapshot_chunks', ['id' => $old->id]);
        $this->assertDatabaseHas('router_snapshot_chunks', ['id' => $fresh->id]);
    }
}
