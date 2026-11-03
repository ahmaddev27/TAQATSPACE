<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Network\HotspotEventProcessor;
use App\Services\Network\RouterSnapshotProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

/**
 * Turns what one router has pushed so far into state: assembles finished
 * snapshots and applies unprocessed events. Dispatched after /sync (the
 * request never waits for it) and again every minute by the scheduler for
 * anything a failed run left behind. Safe to run twice: both steps only touch
 * rows that are not yet marked as processed.
 */
class ProcessRouterBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'ingest';

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public readonly string $routerId)
    {
        $this->onQueue(self::QUEUE);
    }

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->routerId))->releaseAfter(15)->expireAfter(120)];
    }

    public function handle(HotspotEventProcessor $events, RouterSnapshotProcessor $snapshots): void
    {
        // Events first: the snapshot is authoritative and reconciles whatever
        // an event of the same period left behind.
        $events->process($this->routerId);
        $snapshots->process($this->routerId);
    }
}
