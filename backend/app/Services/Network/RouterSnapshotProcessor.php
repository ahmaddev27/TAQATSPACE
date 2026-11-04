<?php

declare(strict_types=1);

namespace App\Services\Network;

use App\Models\RouterSnapshot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Assembles every complete, not yet assembled snapshot of a router. Only the
 * newest one is applied to `device_presence`; older ones are just stamped,
 * because applying them would roll the "connected now" state back in time.
 */
class RouterSnapshotProcessor
{
    public function __construct(
        private readonly SnapshotAssembler $assembler,
        private readonly DevicePresenceService $presence,
        private readonly SessionBuilder $sessions,
    ) {}

    /** @return int number of snapshots applied */
    public function process(string $routerId): int
    {
        $pending = $this->pending($routerId);

        if ($pending->isEmpty()) {
            return 0;
        }

        $newest = $pending->first();
        $supersededByAssembled = $this->hasNewerAssembled($routerId, $newest);

        $this->stampSuperseded($pending->slice(1));

        if ($supersededByAssembled) {
            $this->stampSuperseded(collect([$newest]));

            return 0;
        }

        return DB::transaction(function () use ($newest): int {
            $assembled = $this->assembler->assemble($newest);

            if ($assembled === null) {
                return 0;
            }

            $this->presence->applySnapshot($assembled);
            $this->sessions->applySnapshot($assembled);

            return 1;
        });
    }

    /** @return Collection<int, RouterSnapshot> newest first */
    private function pending(string $routerId): Collection
    {
        return RouterSnapshot::query()
            ->where('router_id', $routerId)
            ->whereNotNull('completed_at')
            ->whereNull('assembled_at')
            ->orderByDesc('taken_at')
            ->get();
    }

    private function hasNewerAssembled(string $routerId, RouterSnapshot $snapshot): bool
    {
        return RouterSnapshot::query()
            ->where('router_id', $routerId)
            ->whereNotNull('assembled_at')
            ->where('taken_at', '>', $snapshot->taken_at)
            ->exists();
    }

    /** @param Collection<int, RouterSnapshot> $snapshots */
    private function stampSuperseded(Collection $snapshots): void
    {
        if ($snapshots->isEmpty()) {
            return;
        }

        RouterSnapshot::query()
            ->whereIn('id', $snapshots->modelKeys())
            ->whereNull('assembled_at')
            ->update(['assembled_at' => now()]);
    }
}
