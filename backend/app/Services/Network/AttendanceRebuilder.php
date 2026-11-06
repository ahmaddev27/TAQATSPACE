<?php

declare(strict_types=1);

namespace App\Services\Network;

use App\Models\AttendanceAffectedDay;
use App\Models\HotspotSession;
use App\Models\Workspace;
use App\Repositories\Eloquent\AttendanceRepository;
use App\Repositories\Eloquent\RouterGapRepository;
use App\Support\Network\GapDays;
use App\Support\Network\LocalDays;
use App\Support\Network\SessionWindow;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Rebuilds attendance_daily from hotspot_sessions: the affected range is
 * deleted and recomputed from scratch, so rebuilding twice gives identical
 * rows and a late event that changes a past day is just another rebuild.
 */
class AttendanceRebuilder
{
    private const DELETE_CHUNK = 500;

    public function __construct(
        private readonly AttendanceRepository $repository,
        private readonly AttendanceCalculator $calculator,
        private readonly RouterGapRepository $gaps,
        private readonly LocalDays $days = new LocalDays,
        private readonly GapDays $gapDays = new GapDays,
    ) {}

    /**
     * @param  string  $from  local date, Y-m-d
     * @param  string  $to  local date, Y-m-d, inclusive
     * @return int rows written
     */
    public function rebuild(Workspace $workspace, string $from, string $to): int
    {
        $zone = $this->days->zone($workspace->timezone);
        $windowStart = CarbonImmutable::createFromTimestampUTC($this->days->startOf($from, $zone));
        $windowEnd = CarbonImmutable::createFromTimestampUTC($this->days->startOf($this->days->next($to), $zone));

        $sessions = $this->repository->sessionsTouching($workspace->getKey(), $windowStart, $windowEnd);
        $gapMinutes = $this->gapDays->minutesByDate($this->gaps->touching($workspace->getKey(), $windowStart, $windowEnd), $zone, $from, $to, now());
        $rows = $this->rowsFor($workspace, $this->intervalsBySubscriber($sessions), $from, $to, $gapMinutes);

        $this->repository->replaceRange($workspace->getKey(), $from, $to, $rows);

        return count($rows);
    }

    /** Rebuilds every day marked since the last run. @return int rows written */
    public function rebuildAffected(): int
    {
        $claimedAt = now();
        $marks = AttendanceAffectedDay::query()->get();
        $workspaces = Workspace::query()->whereIn('id', $marks->pluck('workspace_id')->unique())->get()->keyBy('id');
        $written = 0;

        foreach ($marks->groupBy('workspace_id') as $workspaceId => $dayMarks) {
            $workspace = $workspaces->get($workspaceId);

            if ($workspace !== null) {
                $dates = $dayMarks->map(static fn (AttendanceAffectedDay $mark): string => $mark->date->toDateString());
                $written += $this->rebuild($workspace, (string) $dates->min(), (string) $dates->max());
            }
        }

        $this->release($marks, $claimedAt);

        return $written;
    }

    /** The nightly safety net: the last `$days` local days of every workspace, whatever was marked. */
    public function rebuildRecent(int $days = 2): int
    {
        $written = 0;

        foreach (Workspace::query()->cursor() as $workspace) {
            $zone = $this->days->zone($workspace->timezone);
            $today = $this->days->dateOf(now()->getTimestamp(), $zone);
            $from = CarbonImmutable::parse($today)->subDays($days - 1)->toDateString();
            $written += $this->rebuild($workspace, $from, $today);
        }

        return $written;
    }

    /**
     * @param  Collection<int, HotspotSession>  $sessions
     * @return array<string, list<array{0: CarbonInterface, 1: CarbonInterface}>>
     */
    private function intervalsBySubscriber(Collection $sessions): array
    {
        $grouped = [];

        foreach ($sessions as $session) {
            $grouped[$session->subscriber_id][] = [$session->started_at, SessionWindow::endOf($session)];
        }

        return $grouped;
    }

    /**
     * @param  array<string, list<array{0: CarbonInterface, 1: CarbonInterface}>>  $bySubscriber
     * @param  array<string, int>  $gapMinutes  local date => minutes without router data
     * @return list<array<string, mixed>>
     */
    private function rowsFor(Workspace $workspace, array $bySubscriber, string $from, string $to, array $gapMinutes): array
    {
        $rows = [];

        foreach ($bySubscriber as $subscriberId => $intervals) {
            foreach ($this->calculator->calculate($intervals, (string) $workspace->timezone) as $day) {
                if ($day->date >= $from && $day->date <= $to) {
                    $rows[] = $this->row($workspace->getKey(), (string) $subscriberId, $day, isset($gapMinutes[$day->date]));
                }
            }
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    private function row(string $workspaceId, string $subscriberId, AttendanceDay $day, bool $hasGap): array
    {
        return [
            'workspace_id' => $workspaceId,
            'subscriber_id' => $subscriberId,
            'date' => $day->date,
            'slots_bitmap' => $day->slotsBitmap,
            'active_actual_minutes' => $day->activeActualMinutes,
            'day_span_minutes' => $day->daySpanMinutes,
            'hour_bucket_hours' => $day->hourBucketHours,
            'half_hour_slots' => $day->halfHourSlots,
            'first_seen_at' => $day->firstSeenAt->format('Y-m-d H:i:s'),
            'last_seen_at' => $day->lastSeenAt->format('Y-m-d H:i:s'),
            'has_gap' => $hasGap,
        ];
    }

    /** @param Collection<int, AttendanceAffectedDay> $marks */
    private function release(Collection $marks, CarbonInterface $claimedAt): void
    {
        foreach ($marks->pluck('id')->chunk(self::DELETE_CHUNK) as $ids) {
            AttendanceAffectedDay::query()->whereIn('id', $ids)->where('marked_at', '<', $claimedAt)->delete();
        }
    }
}
