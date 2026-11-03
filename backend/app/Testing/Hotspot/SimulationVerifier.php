<?php

declare(strict_types=1);

namespace App\Testing\Hotspot;

use App\Enums\RouterCommandStatus;
use App\Models\HotspotEvent;
use App\Models\RouterCommand;

/**
 * Compares what the simulated routers say they generated with what the
 * server stored. Reads only; one query per table, none per router or event.
 */
final class SimulationVerifier
{
    /**
     * @param  array<string, RouterSimulator>  $simulators  router id => simulator
     * @return array{stored: int, lost: int, dropped: int, duplicatesStored: int, seqGaps: int, maxClockError: int, commandsQueued: int, commandsAcknowledged: int}
     */
    public function verify(array $simulators): array
    {
        $routerIds = array_keys($simulators);
        $rows = 0;
        $stored = [];
        $maxClockError = 0;

        foreach (HotspotEvent::query()->whereIn('router_id', $routerIds)->cursor() as $event) {
            $rows++;
            $key = $event->router_id.'|'.$event->event_uid;
            $stored[$key] = true;
            $expected = $simulators[$event->router_id]->generatedEvents()[$event->event_uid] ?? null;

            if ($expected !== null) {
                $maxClockError = max($maxClockError, abs($event->occurred_at->getTimestamp() - $expected));
            }
        }

        $commands = $this->commandCounts($routerIds);

        return [
            'stored' => count($stored),
            'lost' => $this->lost($simulators, $stored),
            'dropped' => array_sum(array_map(static fn (RouterSimulator $simulator): int => count($simulator->droppedEventUids()), $simulators)),
            'duplicatesStored' => $rows - count($stored),
            'seqGaps' => $this->seqGaps(array_keys($stored + $this->droppedKeys($simulators))),
            'maxClockError' => $maxClockError,
            'commandsQueued' => array_sum($commands),
            'commandsAcknowledged' => $commands[RouterCommandStatus::Acknowledged->value] ?? 0,
        ];
    }

    /**
     * @param  array<string, RouterSimulator>  $simulators
     * @param  array<string, true>  $stored
     */
    private function lost(array $simulators, array $stored): int
    {
        $lost = 0;

        foreach ($simulators as $routerId => $simulator) {
            $dropped = array_flip($simulator->droppedEventUids());

            foreach (array_keys($simulator->generatedEvents()) as $uid) {
                $lost += isset($stored[$routerId.'|'.$uid]) || isset($dropped[$uid]) ? 0 : 1;
            }
        }

        return $lost;
    }

    /**
     * Events inside a batch the router gave up on were refused by the server, not lost on the way.
     *
     * @param  array<string, RouterSimulator>  $simulators
     * @return array<string, true>
     */
    private function droppedKeys(array $simulators): array
    {
        $keys = [];

        foreach ($simulators as $routerId => $simulator) {
            foreach ($simulator->droppedEventUids() as $uid) {
                $keys[$routerId.'|'.$uid] = true;
            }
        }

        return $keys;
    }

    /**
     * The persistent seq must run without holes across reboots, so the stored
     * seq numbers of a router form one unbroken range.
     *
     * @param  list<string>  $storedKeys  `routerId|bootId-seq`
     */
    private function seqGaps(array $storedKeys): int
    {
        $seqs = [];

        foreach ($storedKeys as $key) {
            [$routerId, $uid] = explode('|', $key, 2);
            $seqs[$routerId][(int) substr($uid, (int) strrpos($uid, '-') + 1)] = true;
        }

        return array_sum(array_map(
            static fn (array $set): int => max(array_keys($set)) - min(array_keys($set)) + 1 - count($set),
            $seqs,
        ));
    }

    /**
     * @param  list<string>  $routerIds
     * @return array<string, int> status => count
     */
    private function commandCounts(array $routerIds): array
    {
        return RouterCommand::query()
            ->withoutWorkspaceScope()
            ->whereIn('router_id', $routerIds)
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(static fn (mixed $total): int => (int) $total)
            ->all();
    }
}
