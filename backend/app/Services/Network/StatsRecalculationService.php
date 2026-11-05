<?php

declare(strict_types=1);

namespace App\Services\Network;

use App\Exceptions\RecalculationInProgressException;
use App\Jobs\RecalculateStatsJob;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Recalculating a range rebuilds its attendance from the sessions and then the
 * statistics from the attendance. One run per workspace at a time: the lock is
 * claimed when the request is accepted, so a second request is refused even
 * while the first is still waiting in the queue. The lock expires on its own
 * if a worker dies without releasing it.
 */
class StatsRecalculationService
{
    private const LOCK_SECONDS = 1800;

    private const STATUS_SECONDS = 604800;

    public function __construct(
        private readonly AttendanceRebuilder $attendance,
        private readonly StatsAggregator $aggregator,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  bool  $audited  false for the routine nightly run, which would otherwise flood the log
     * @param  bool  $inline  run now instead of queueing on the heavy queue
     *
     * @throws RecalculationInProgressException
     */
    public function request(Workspace $workspace, string $from, string $to, ?User $actor = null, bool $audited = true, bool $inline = false): RecalculationStatus
    {
        if (! Cache::add($this->lockKey($workspace), now()->getTimestamp(), self::LOCK_SECONDS)) {
            throw new RecalculationInProgressException;
        }

        $status = RecalculationStatus::queued($from, $to);
        $this->store($workspace, $status);

        if ($audited) {
            $this->audit->log('data.recalculated', $workspace, null, ['from' => $from, 'to' => $to], $actor);
        }

        $this->dispatch($workspace, $from, $to, $inline);

        return $inline ? $this->status($workspace) : $status;
    }

    /** Called by the job; always leaves the lock released and the status final. */
    public function run(Workspace $workspace, string $from, string $to): void
    {
        $status = $this->status($workspace);
        $this->store($workspace, $status->running());

        try {
            $this->attendance->rebuild($workspace, $from, $to);
            $this->store($workspace, $status->running()->completed($this->aggregator->aggregate($workspace, $from, $to)));
        } catch (Throwable $e) {
            $this->fail($workspace);

            throw $e;
        } finally {
            Cache::forget($this->lockKey($workspace));
        }
    }

    /** Idempotent: also reached from the job's failure hook when a worker was killed mid-run. */
    public function fail(Workspace $workspace): void
    {
        $this->store($workspace, $this->status($workspace)->failed());
        Cache::forget($this->lockKey($workspace));
    }

    public function status(Workspace $workspace): RecalculationStatus
    {
        $stored = Cache::get($this->statusKey($workspace));

        return is_array($stored) ? RecalculationStatus::fromArray($stored) : RecalculationStatus::idle();
    }

    private function dispatch(Workspace $workspace, string $from, string $to, bool $inline): void
    {
        try {
            $inline
                ? RecalculateStatsJob::dispatchSync((string) $workspace->getKey(), $from, $to)
                : RecalculateStatsJob::dispatch((string) $workspace->getKey(), $from, $to);
        } catch (Throwable $e) {
            // A request that never reached the queue must not block the next one for half an hour.
            $this->fail($workspace);

            throw $e;
        }
    }

    private function store(Workspace $workspace, RecalculationStatus $status): void
    {
        Cache::put($this->statusKey($workspace), $status->toArray(), self::STATUS_SECONDS);
    }

    private function lockKey(Workspace $workspace): string
    {
        return 'stats-recalculation:lock:'.$workspace->getKey();
    }

    private function statusKey(Workspace $workspace): string
    {
        return 'stats-recalculation:status:'.$workspace->getKey();
    }
}
