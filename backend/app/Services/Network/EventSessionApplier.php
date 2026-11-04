<?php

declare(strict_types=1);

namespace App\Services\Network;

use App\Enums\SessionEndReason;
use App\Enums\SessionSource;
use App\Models\Device;
use App\Models\HotspotEvent;
use App\Models\HotspotSession;
use App\Models\Router;
use App\Support\Network\MacAddress;
use App\Support\Router\RouterOsDuration;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Applies login/logout events to a ledger. Every rule is written so that
 * replaying an event, or receiving it late, leaves the ledger as it was:
 * a login that falls inside a known session, or a logout that matches a
 * known end, changes nothing.
 */
class EventSessionApplier
{
    /**
     * @param  Collection<int, HotspotEvent>  $events  ordered by time
     * @param  Collection<string, Device>  $devices  keyed by normalized MAC
     * @return BootMark the boot the ledger is now built under
     */
    public function apply(Router $router, SessionLedger $ledger, Collection $events, Collection $devices): BootMark
    {
        $mark = new BootMark($router->sessions_boot_id, $router->sessions_boot_since);

        foreach ($events as $event) {
            $mark = $this->followBoot($ledger, $mark, $this->bootOf($event), $event);
            $device = $devices->get((string) MacAddress::normalize($event->mac));

            if ($device === null) {
                continue;
            }

            $event->type === 'login'
                ? $this->login($router, $ledger, $device, $event)
                : $this->logout($router, $ledger, $device, $event);
        }

        return $mark;
    }

    /**
     * A newer boot means every session still open lost its router: close them
     * where the router last vouched for them. A late event of an older boot
     * says nothing about the current one and moves nothing.
     */
    private function followBoot(SessionLedger $ledger, BootMark $current, string $eventBoot, HotspotEvent $event): BootMark
    {
        if ($current->id === $eventBoot) {
            return $current;
        }

        if ($current->since !== null && $event->occurred_at->lessThan($current->since)) {
            return $current;
        }

        if ($current->id !== null) {
            foreach ($ledger->openSessions() as $session) {
                $ledger->endAtLastContact($session, SessionEndReason::Reboot);
            }
        }

        return new BootMark($eventBoot, $event->occurred_at);
    }

    /** The event identity is `boot_id-seq`. */
    private function bootOf(HotspotEvent $event): string
    {
        $dash = strrpos($event->event_uid, '-');

        return $dash === false ? $event->event_uid : substr($event->event_uid, 0, $dash);
    }

    private function login(Router $router, SessionLedger $ledger, Device $device, HotspotEvent $event): void
    {
        $at = $event->occurred_at;
        $known = $ledger->latestAtOrBefore($device->getKey(), $at);

        if ($known !== null && ($known->isOpen() || $known->ended_at->greaterThanOrEqualTo($at))) {
            $ledger->touch($known, $at);

            return;
        }

        // An open session that started later means this login is an older, already superseded one.
        if ($ledger->open($device->getKey()) === null) {
            $ledger->begin($router, $device, $at, $at, SessionSource::Event);
        }
    }

    private function logout(Router $router, SessionLedger $ledger, Device $device, HotspotEvent $event): void
    {
        $at = $event->occurred_at;
        $session = $ledger->latestAtOrBefore($device->getKey(), $at);
        $uptime = RouterOsDuration::toSeconds($event->data['uptime'] ?? null);

        if ($session !== null && $session->isOpen()) {
            $this->close($ledger, $session, $event);

            return;
        }

        if ($session !== null && $session->ended_at->greaterThanOrEqualTo($at)) {
            return;
        }

        if ($session !== null && $this->isSameLogin($session, $at, $uptime)) {
            $this->close($ledger, $session, $event);

            return;
        }

        $this->recordLogoutWithoutLogin($router, $ledger, $device, $event, $session, $uptime);
    }

    /** An inferred ending (timeout, missing from snapshots) is corrected by the real logout of the same login. */
    private function isSameLogin(HotspotSession $closed, CarbonInterface $at, int $uptime): bool
    {
        return $closed->end_reason?->isInferred() === true
            && ($uptime === 0 || $at->copy()->subSeconds($uptime)->lessThanOrEqualTo($closed->ended_at));
    }

    private function close(SessionLedger $ledger, HotspotSession $session, HotspotEvent $event): void
    {
        $ledger->end($session, $event->occurred_at, SessionEndReason::Logout);

        $session->bytes_in = max($session->bytes_in, (int) ($event->data['bytes_in'] ?? 0));
        $session->bytes_out = max($session->bytes_out, (int) ($event->data['bytes_out'] ?? 0));
    }

    /**
     * Its login was lost. The uptime on the logout still says how long the
     * device was connected, so the session is recovered from it; without an
     * uptime, or if it would overlap the previous session, nothing is invented.
     */
    private function recordLogoutWithoutLogin(Router $router, SessionLedger $ledger, Device $device, HotspotEvent $event, ?HotspotSession $previous, int $uptime): void
    {
        $startedAt = $event->occurred_at->copy()->subSeconds($uptime);

        if ($uptime === 0 || ($previous?->ended_at !== null && $startedAt->lessThan($previous->ended_at))) {
            return;
        }

        $session = $ledger->begin($router, $device, $startedAt, $event->occurred_at, SessionSource::Event);
        $this->close($ledger, $session, $event);
    }
}
