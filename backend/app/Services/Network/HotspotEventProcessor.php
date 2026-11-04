<?php

declare(strict_types=1);

namespace App\Services\Network;

use App\Events\HotspotEventsProcessed;
use App\Models\HotspotEvent;
use App\Models\Router;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Turns stored raw events into state. Each batch is applied and marked
 * `processed_at` in one transaction, so a failure leaves the batch untouched
 * for the next run and re-running never applies an event twice.
 */
class HotspotEventProcessor
{
    public const BATCH_SIZE = 500;

    public function __construct(
        private readonly DevicePresenceService $presence,
        private readonly DeviceResolver $devices,
    ) {}

    /** @return int number of events processed */
    public function process(string $routerId): int
    {
        $router = Router::query()->withoutWorkspaceScope()->findOrFail($routerId);
        $total = 0;

        $this->rebuildUnbuilt($routerId);

        while (($events = $this->nextBatch($routerId))->isNotEmpty()) {
            DB::transaction(function () use ($router, $routerId, $events): void {
                $this->devices->resolveBatch($router, $events);
                $this->presence->applyEvents($routerId, $events);
                $this->markProcessed($events);
            });

            // Session building (3.3) listens for this hand-off.
            HotspotEventsProcessed::dispatch($routerId, $events->pluck('id')->all());
            $total += $events->count();
        }

        return $total;
    }

    /**
     * Applied events whose sessions were never built (the previous run died
     * after marking them) are handed over again, oldest first. Building is
     * idempotent and marks them built, so each retry makes progress.
     */
    private function rebuildUnbuilt(string $routerId): void
    {
        $after = 0;

        while (($ids = $this->unbuiltEventIds($routerId, $after)) !== []) {
            HotspotEventsProcessed::dispatch($routerId, $ids);
            $after = end($ids);
        }
    }

    /** @return list<int> */
    private function unbuiltEventIds(string $routerId, int $after): array
    {
        return HotspotEvent::query()
            ->where('router_id', $routerId)
            ->where('id', '>', $after)
            ->whereNotNull('processed_at')
            ->whereNull('sessions_built_at')
            ->orderBy('id')
            ->limit(self::BATCH_SIZE)
            ->pluck('id')
            ->all();
    }

    /** @return Collection<int, HotspotEvent> */
    private function nextBatch(string $routerId): Collection
    {
        return HotspotEvent::query()
            ->where('router_id', $routerId)
            ->whereNull('processed_at')
            ->orderBy('id')
            ->limit(self::BATCH_SIZE)
            ->get();
    }

    /** @param Collection<int, HotspotEvent> $events */
    private function markProcessed(Collection $events): void
    {
        HotspotEvent::query()->whereIn('id', $events->pluck('id'))->update(['processed_at' => now()]);
    }
}
