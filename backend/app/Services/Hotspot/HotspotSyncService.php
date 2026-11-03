<?php

declare(strict_types=1);

namespace App\Services\Hotspot;

use App\Enums\RouterStatus;
use App\Models\Router;
use App\Services\Router\RouterCommandService;
use App\Services\Router\RouterSecurityService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Handles one router sync batch: stores the raw events and snapshot chunk in a
 * single transaction (never via the queue) and answers with the plain-text
 * reply. The reply for a (router, batch_id) pair is cached so a retried batch
 * gets the identical answer without being processed twice. The same
 * transaction applies the batch's acknowledgements and claims the queued
 * commands (RouterCommandService). The reply echoes the nonce the router
 * sent; JSON callers without one get a random value.
 */
class HotspotSyncService
{
    public const RESPONSE_TTL_MINUTES = 10;

    public function __construct(
        private readonly HotspotEventStore $events,
        private readonly RouterSnapshotStore $snapshots,
        private readonly SyncResponseBuilder $responses,
        private readonly RouterCommandService $commands,
        private readonly RouterSecurityService $security,
    ) {}

    public function handle(Router $router, SyncPayload $payload): string
    {
        $cacheKey = "router-sync:{$router->getKey()}:{$payload->batchId}";

        $cached = Cache::get($cacheKey);

        if (is_string($cached)) {
            return $cached;
        }

        $nonce = $payload->nonce ?? Str::lower(Str::random(16));
        $commands = $this->persist($router, $payload, now(), SyncResponseBuilder::commandBudget($nonce));

        $response = $this->responses->build($nonce, $commands);

        Cache::put($cacheKey, $response, now()->addMinutes(self::RESPONSE_TTL_MINUTES));

        return $response;
    }

    /**
     * @return list<string> the `C|` lines for the reply
     */
    private function persist(Router $router, SyncPayload $payload, CarbonInterface $receivedAt, int $commandBudget): array
    {
        // Routers without a clock battery drift after power cuts: shift every
        // router timestamp by the difference between server and router "now".
        $offset = $receivedAt->getTimestamp() - $payload->routerNow;

        return DB::transaction(function () use ($router, $payload, $receivedAt, $offset, $commandBudget): array {
            $this->events->store($router, $payload, $payload->events, $offset, $receivedAt);

            if ($payload->snapshot !== null) {
                $this->snapshots->store($router, $payload->snapshot, $offset, $receivedAt);
            }

            $this->touchRouter($router, $payload, $offset, $receivedAt);
            $this->security->bindSerial($router, $payload->serial);

            $lines = $this->commands->syncCycle($router, $payload->acks, $commandBudget, $payload->mode === 'poll-only');
            $this->security->revokePreviousKeyIfConfirmed($router);

            return $lines;
        });
    }

    private function touchRouter(Router $router, SyncPayload $payload, int $offset, CarbonInterface $receivedAt): void
    {
        $router->update([
            'status' => RouterStatus::Online,
            'last_seen_at' => $receivedAt,
            'last_seq' => max((int) $router->last_seq, $payload->seq),
            'clock_offset_seconds' => $offset,
            'routeros_version' => $payload->routerosVersion ?? $router->routeros_version,
            'script_version' => $payload->scriptVersion ?? $router->script_version,
        ]);
    }
}
