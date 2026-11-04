<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\HotspotEventsProcessed;
use App\Services\Network\SessionBuilder;

/**
 * Hand-off from the event processor: turns the batch just applied to the
 * connected-now table into sessions. Runs inline, in the ingest job.
 */
class BuildSessionsFromEvents
{
    public function __construct(private readonly SessionBuilder $sessions) {}

    public function handle(HotspotEventsProcessed $event): void
    {
        $this->sessions->applyEvents($event->routerId, $event->eventIds);
    }
}
