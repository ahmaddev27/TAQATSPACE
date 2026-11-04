<?php

declare(strict_types=1);

namespace App\Services\Network;

use App\Models\RouterSnapshot;
use App\Models\RouterSnapshotChunk;

/**
 * Puts the chunks of a complete snapshot back together in `chunk_index`
 * order. The `assembled_at` stamp is claimed with one conditional UPDATE, so
 * concurrent workers assemble a snapshot exactly once. Call it inside the
 * transaction that applies the result: a failure then rolls the claim back.
 */
class SnapshotAssembler
{
    public function assemble(RouterSnapshot $snapshot): ?AssembledSnapshot
    {
        $payloads = RouterSnapshotChunk::query()
            ->where('router_snapshot_id', $snapshot->getKey())
            ->orderBy('chunk_index')
            ->pluck('payload');

        if ($payloads->count() < $snapshot->total_chunks || ! $this->claim($snapshot)) {
            return null;
        }

        $snapshot->loadMissing('router');

        return new AssembledSnapshot(
            routerId: $snapshot->router_id,
            workspaceId: $snapshot->router->workspace_id,
            takenAt: $snapshot->taken_at,
            rows: $this->parseRows($payloads->implode("\n")),
        );
    }

    private function claim(RouterSnapshot $snapshot): bool
    {
        $claimed = RouterSnapshot::query()
            ->whereKey($snapshot->getKey())
            ->whereNull('assembled_at')
            ->update(['assembled_at' => now()]);

        return $claimed === 1;
    }

    /**
     * @return list<SnapshotRow>
     */
    private function parseRows(string $data): array
    {
        $rows = [];

        foreach (preg_split('/\R/', $data) ?: [] as $line) {
            $row = SnapshotRow::fromLine($line);

            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}
