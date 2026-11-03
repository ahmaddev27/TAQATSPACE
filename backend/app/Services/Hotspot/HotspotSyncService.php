<?php

declare(strict_types=1);

namespace App\Services\Hotspot;

use App\Enums\RouterStatus;
use App\Jobs\ProcessRouterBatch;
use App\Models\Router;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Handles one router sync batch: stores the raw events and snapshot chunk in a
 * single transaction (never via the queue) and answers with the plain-text
 * reply. The reply for a (router, batch_id) pair is cached so a retried batch
 * gets the identical answer without being processed twice. The reply echoes
 * the nonce the router sent; JSON callers without one get a random value.
 */
class HotspotSyncService
{
    public const RESPONSE_TTL_MINUTES = 10;

    public function __construct(
        private readonly HotspotEventStore $events,
        private readonly RouterSnapshotStore $snapshots,
        private readonly SyncResponseBuilder $responses,
    ) {}

    public function handle(Router $router, SyncPayload $payload): string
    {
        $cacheKey = "router-sync:{$router->getKey()}:{$payload->batchId}";

        $cached = Cache::get($cacheKey);

        if (is_string($cached)) {
            return $cached;
        }

        $this->persist($router, $payload, now());
        $this->dispatchProcessing($router);

        $response = $this->responses->build($payload->nonce ?? Str::lower(Str::random(16)));

        Cache::put($cacheKey, $response, now()->addMinutes(self::RESPONSE_TTL_MINUTES));

        return $response;
    }

    /**
     * The batch is already stored, so a queue outage must not fail the sync:
     * the per-minute fallback picks the unprocessed rows up later.
     */
    private function dispatchProcessing(Router $router): void
    {
        try {
            ProcessRouterBatch::dispatch((string) $router->getKey());
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function persist(Router $router, SyncPayload $payload, CarbonInterface $receivedAt): void
    {
        // Routers without a clock battery drift after power cuts: shift every
        // router timestamp by the difference between server and router "now".
        $offset = $receivedAt->getTimestamp() - $payload->routerNow;

        DB::transaction(function () use ($router, $payload, $receivedAt, $offset): void {
            $this->events->store($router, $payload, $payload->events, $offset, $receivedAt);

            if ($payload->snapshot !== null) {
                $this->snapshots->store($router, $payload->snapshot, $offset, $receivedAt);
            }

            $this->touchRouter($router, $payload, $offset, $receivedAt);
        });
    }

    private function touchRouter(Router $router, SyncPayload $payload, int $offset, CarbonInterface $receivedAt): void
    {
        $router->update([
            'status' => RouterStatus::Online,
            'last_seen_at' => $receivedAt,
            'last_seq' => max((int) $router->last_seq, $payload->seq),
            'clock_offset_seconds' => $offset,
            'serial_number' => $router->serial_number ?? $payload->serial,
            'routeros_version' => $payload->routerosVersion ?? $router->routeros_version,
            'script_version' => $payload->scriptVersion ?? $router->script_version,
        ]);
    }
}
