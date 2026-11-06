<?php

declare(strict_types=1);

namespace App\Services\Network;

use App\Models\AttendanceAffectedDay;
use App\Models\HotspotSession;
use App\Models\Workspace;
use App\Support\Network\LocalDays;

/**
 * The work list for attendance rebuilds: every session change marks the
 * local days it touches (before and after the change, so a late event that
 * moves a session still re-covers the day it left).
 */
class AttendanceAffectedDays
{
    private const TIME_COLUMNS = ['subscriber_id', 'started_at', 'ended_at', 'last_seen_at'];

    public function __construct(private readonly LocalDays $days = new LocalDays) {}

    /**
     * @param  list<HotspotSession>  $created
     * @param  list<HotspotSession>  $changed
     */
    public function markSessions(string $workspaceId, array $created, array $changed): void
    {
        $spans = [];

        foreach ($created as $session) {
            $spans[] = $this->spanOf($session->started_at, $session->ended_at ?? $session->last_seen_at, $session->subscriber_id);
        }

        foreach ($changed as $session) {
            if ($session->isDirty(self::TIME_COLUMNS)) {
                $spans[] = $this->spanOf($session->started_at, $session->ended_at ?? $session->last_seen_at, $session->subscriber_id);
                $spans[] = $this->spanOf($session->getOriginal('started_at'), $session->getOriginal('ended_at') ?? $session->getOriginal('last_seen_at'), $session->getOriginal('subscriber_id'));
            }
        }

        $this->mark($workspaceId, array_filter($spans));
    }

    /** @param list<string> $dates local `Y-m-d` */
    public function markDates(string $workspaceId, array $dates): void
    {
        $now = now();
        $rows = array_map(static fn (string $date): array => ['workspace_id' => $workspaceId, 'date' => $date, 'marked_at' => $now], array_values(array_unique($dates)));

        if ($rows !== []) {
            AttendanceAffectedDay::query()->upsert($rows, ['workspace_id', 'date'], ['marked_at']);
        }
    }

    /** @param array<int, array{0: int, 1: int}> $spans timestamps; every local day each one touches is marked */
    public function markSpans(string $workspaceId, array $spans): void
    {
        $this->mark($workspaceId, $spans);
    }

    /**
     * @param  array<int, array{0: int, 1: int}>  $spans  timestamps
     */
    private function mark(string $workspaceId, array $spans): void
    {
        if ($spans === []) {
            return;
        }

        $zone = $this->days->zone(Workspace::query()->whereKey($workspaceId)->value('timezone'));
        $dates = [];

        foreach ($spans as [$from, $to]) {
            $last = $this->days->dateOf(max($from, $to), $zone);

            for ($date = $this->days->dateOf($from, $zone); $date <= $last; $date = $this->days->next($date)) {
                $dates[] = $date;
            }
        }

        $this->markDates($workspaceId, $dates);
    }

    /** @return array{0: int, 1: int}|null null for a session nobody is attached to */
    private function spanOf(mixed $start, mixed $end, ?string $subscriberId): ?array
    {
        if ($subscriberId === null || $start === null || $end === null) {
            return null;
        }

        return [$this->timestamp($start), $this->timestamp($end)];
    }

    private function timestamp(mixed $value): int
    {
        return $value instanceof \DateTimeInterface ? $value->getTimestamp() : (int) strtotime((string) $value);
    }
}
