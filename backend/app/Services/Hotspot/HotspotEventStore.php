<?php

declare(strict_types=1);

namespace App\Services\Hotspot;

use App\Models\HotspotEvent;
use App\Models\Router;
use Carbon\CarbonInterface;

/**
 * Bulk raw-event storage. A re-sent event is skipped by the
 * UNIQUE(router_id, event_uid) key, so retries never duplicate rows.
 */
class HotspotEventStore
{
    /**
     * @param  list<SyncEvent>  $events
     */
    public function store(Router $router, SyncPayload $payload, array $events, int $offsetSeconds, CarbonInterface $receivedAt): void
    {
        if ($events === []) {
            return;
        }

        $rows = array_map(fn (SyncEvent $event): array => [
            'router_id' => $router->getKey(),
            'workspace_id' => $router->workspace_id,
            'event_uid' => $payload->bootId.'-'.$event->seq,
            'batch_id' => $payload->batchId,
            'type' => $event->type,
            'mac' => $event->mac,
            'ip' => $event->ip,
            'username' => $event->username,
            'data' => $event->data === null ? null : json_encode($event->data),
            'occurred_at' => $receivedAt->copy()->setTimestamp($event->routerAt + $offsetSeconds),
            'received_at' => $receivedAt,
        ], $events);

        HotspotEvent::query()->insertOrIgnore($rows);
    }
}
