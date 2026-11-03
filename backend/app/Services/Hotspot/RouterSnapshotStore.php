<?php

declare(strict_types=1);

namespace App\Services\Hotspot;

use App\Models\Router;
use App\Models\RouterSnapshot;
use App\Models\RouterSnapshotChunk;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Stores snapshot chunks as they arrive. Chunk re-sends are ignored, and the
 * snapshot is marked complete once every chunk index is present.
 */
class RouterSnapshotStore
{
    public function store(Router $router, SyncSnapshot $chunk, int $offsetSeconds, CarbonInterface $receivedAt): void
    {
        $snapshot = $this->findOrCreate($router, $chunk, $offsetSeconds, $receivedAt);

        RouterSnapshotChunk::query()->insertOrIgnore([
            'router_snapshot_id' => $snapshot->getKey(),
            'chunk_index' => $chunk->index,
            'payload' => $chunk->data,
            'created_at' => $receivedAt,
        ]);

        $received = RouterSnapshotChunk::query()->where('router_snapshot_id', $snapshot->getKey())->count();

        $snapshot->update([
            'received_chunks' => $received,
            'completed_at' => $snapshot->completed_at ?? ($received >= $snapshot->total_chunks ? $receivedAt : null),
        ]);
    }

    private function findOrCreate(Router $router, SyncSnapshot $chunk, int $offsetSeconds, CarbonInterface $receivedAt): RouterSnapshot
    {
        RouterSnapshot::query()->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'router_id' => $router->getKey(),
            'snapshot_id' => $chunk->id,
            'total_chunks' => $chunk->total,
            'received_chunks' => 0,
            'taken_at' => $receivedAt->copy()->setTimestamp($chunk->routerTakenAt + $offsetSeconds),
            'created_at' => $receivedAt,
            'updated_at' => $receivedAt,
        ]);

        return RouterSnapshot::query()
            ->where('router_id', $router->getKey())
            ->where('snapshot_id', $chunk->id)
            ->firstOrFail();
    }
}
