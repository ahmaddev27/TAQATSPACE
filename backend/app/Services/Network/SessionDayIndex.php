<?php

declare(strict_types=1);

namespace App\Services\Network;

use App\Models\HotspotSession;
use App\Support\Network\LocalDays;
use App\Support\Network\SessionWindow;
use DateTimeZone;
use Illuminate\Support\Collection;

/**
 * Sessions laid out on workspace-local days: each session is clipped to every
 * day it spans (for the concurrent peak) and its traffic is credited to the
 * day it started on, since the router reports bytes per session, not per day.
 */
final class SessionDayIndex
{
    /** @var array<string, list<array{0: int, 1: int}>> date => [start, end) timestamps */
    private array $intervals = [];

    /** @var array<string, array{0: int, 1: int}> date => [bytes in, bytes out] */
    private array $traffic = [];

    /** @var array<string, array{0: int, 1: int}> "subscriber|month" => [bytes in, bytes out] */
    private array $subscriberTraffic = [];

    /**
     * @param  Collection<int, HotspotSession>  $sessions
     * @param  string  $windowFrom  first local date that is kept, Y-m-d
     * @param  string  $windowTo  last local date that is kept, Y-m-d
     */
    public function __construct(
        Collection $sessions,
        private readonly DateTimeZone $zone,
        private readonly string $windowFrom,
        private readonly string $windowTo,
        private readonly LocalDays $days = new LocalDays,
    ) {
        foreach ($sessions as $session) {
            $this->add($session);
        }
    }

    /** @return list<array{0: int, 1: int}> */
    public function intervals(string $date): array
    {
        return $this->intervals[$date] ?? [];
    }

    /** @return array{0: int, 1: int} */
    public function traffic(string $date): array
    {
        return $this->traffic[$date] ?? [0, 0];
    }

    /** @return array{0: int, 1: int} */
    public function subscriberTraffic(string $subscriberId, string $month): array
    {
        return $this->subscriberTraffic[$subscriberId.'|'.$month] ?? [0, 0];
    }

    private function add(HotspotSession $session): void
    {
        $start = $session->started_at->getTimestamp();
        $end = max($start, SessionWindow::endOf($session)->getTimestamp());
        $startDate = $this->days->dateOf($start, $this->zone);

        $this->addTraffic($session, $startDate);

        for ($date = max($startDate, $this->windowFrom); $date <= $this->windowTo; $date = $this->days->next($date)) {
            $dayStart = $this->days->startOf($date, $this->zone);
            $dayEnd = $this->days->startOf($this->days->next($date), $this->zone);

            if ($dayStart >= $end && $date > $startDate) {
                break;
            }

            $this->intervals[$date][] = [max($start, $dayStart), min($end, $dayEnd)];
        }
    }

    private function addTraffic(HotspotSession $session, string $startDate): void
    {
        if ($startDate < $this->windowFrom || $startDate > $this->windowTo) {
            return;
        }

        $this->traffic[$startDate] = $this->sum($this->traffic[$startDate] ?? [0, 0], $session);

        if ($session->subscriber_id !== null) {
            $key = $session->subscriber_id.'|'.substr($startDate, 0, 7).'-01';
            $this->subscriberTraffic[$key] = $this->sum($this->subscriberTraffic[$key] ?? [0, 0], $session);
        }
    }

    /**
     * @param  array{0: int, 1: int}  $total
     * @return array{0: int, 1: int}
     */
    private function sum(array $total, HotspotSession $session): array
    {
        return [$total[0] + $session->bytes_in, $total[1] + $session->bytes_out];
    }
}
