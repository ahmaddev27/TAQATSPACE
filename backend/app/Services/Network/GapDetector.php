<?php

declare(strict_types=1);

namespace App\Services\Network;

use App\Enums\GapReason;
use App\Enums\WorkspaceFeature;
use App\Models\Router;
use App\Models\RouterGap;
use App\Repositories\Eloquent\RouterGapRepository;
use App\Services\FeatureFlagService;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Finds the periods in which a router's data is missing, so attendance and
 * the statistics can tell "nobody came" from "we were not listening".
 *
 * Two entry points: the scheduler opens a gap for a router that went quiet
 * (detectSilent), and every /sync closes the router's open gap or records one
 * it can see only in hindsight, a reboot or a clock jump (recordSync). A router
 * has at most one open gap, so repeating either call changes nothing.
 * Every change marks the local days it touches for an attendance rebuild.
 */
class GapDetector
{
    public function __construct(
        private readonly RouterGapRepository $gaps,
        private readonly AttendanceAffectedDays $affectedDays,
        private readonly FeatureFlagService $flags,
    ) {}

    /** @return int gaps opened; only routers of workspaces with the network module are looked at */
    public function detectSilent(): int
    {
        $now = now();
        $cutoff = $now->copy()->subMinutes($this->thresholdMinutes());
        $opened = $this->openGaps($this->gaps->silentWithoutGap($cutoff), $cutoff, $now);

        foreach ($opened->groupBy('workspace_id') as $workspaceId => $routers) {
            $this->affectedDays->markSpans((string) $workspaceId, $routers->map(fn (Router $router): array => [$router->last_seen_at->getTimestamp(), $now->getTimestamp()])->all());
        }

        return $opened->count();
    }

    /**
     * Opens a gap for each candidate that is still silent and still has none. The check and the
     * insert run under a lock on the router rows, so a sync or a second detector run that
     * overlaps this one cannot open a second gap for the same router.
     *
     * @param  Collection<int, Router>  $candidates
     * @return Collection<int, Router> the routers a gap was opened for
     */
    public function openGaps(Collection $candidates, CarbonInterface $cutoff, CarbonInterface $now): Collection
    {
        if ($candidates->isEmpty()) {
            return $candidates;
        }

        return DB::transaction(function () use ($candidates, $cutoff, $now): Collection {
            $locked = $this->gaps->lockRouters($candidates->pluck('id')->all());
            $taken = array_flip($this->gaps->routerIdsWithOpenGap($locked->pluck('id')->all()));
            $routers = $locked->filter(fn (Router $router): bool => ! isset($taken[$router->getKey()]) && $router->last_seen_at !== null && $router->last_seen_at->lte($cutoff))->values();

            if ($routers->isNotEmpty()) {
                RouterGap::query()->withoutWorkspaceScope()->insert($routers->map(fn (Router $router): array => $this->openRow($router, $now))->all());
            }

            return $routers;
        });
    }

    /**
     * An ongoing gap grows every day, but its days are otherwise marked only when it closes; the
     * nightly pass marks them again so attendance and the daily gap minutes follow the gap.
     *
     * @return int open gaps re-marked
     */
    public function remarkOpenGaps(): int
    {
        $now = now()->getTimestamp();
        $open = $this->gaps->openInNetworkWorkspaces();

        foreach ($open->groupBy('workspace_id') as $workspaceId => $gaps) {
            $this->affectedDays->markSpans((string) $workspaceId, $gaps->map(fn (RouterGap $gap): array => [$gap->started_at->getTimestamp(), $now])->all());
        }

        return $open->count();
    }

    /**
     * Called from the sync handler before the router row takes the new values,
     * so `$router` still describes the previous sync.
     */
    public function recordSync(Router $router, string $bootId, int $clockOffset, CarbonInterface $receivedAt): void
    {
        if ($router->last_seen_at === null || ! $this->flags->isEnabled($router->workspace_id, WorkspaceFeature::Network)) {
            return;
        }

        $reason = $this->reasonFor($router, $bootId, $clockOffset, $receivedAt);

        DB::transaction(function () use ($router, $reason, $receivedAt): void {
            $this->gaps->lockRouters([(string) $router->getKey()]);
            $open = $this->gaps->openFor((string) $router->getKey());

            if ($open !== null) {
                $this->close($open, $reason ?? $open->reason, $receivedAt);
            } elseif ($reason !== null) {
                $this->record($router, $reason, $router->last_seen_at, $receivedAt);
            }
        });
    }

    /** @return array<string, mixed> */
    private function openRow(Router $router, CarbonInterface $now): array
    {
        return [
            'id' => (string) Str::uuid(),
            'router_id' => $router->getKey(),
            'workspace_id' => $router->workspace_id,
            'started_at' => $router->last_seen_at,
            'ended_at' => null,
            'reason' => GapReason::NoSync->value,
            'resolved_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /** A gap that is already over, noticed only now. */
    public function record(Router $router, GapReason $reason, CarbonInterface $startedAt, CarbonInterface $endedAt): RouterGap
    {
        $gap = RouterGap::query()->withoutWorkspaceScope()->create([
            'router_id' => $router->getKey(),
            'workspace_id' => $router->workspace_id,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'reason' => $reason,
            'resolved_at' => now(),
        ]);

        $this->markDays($gap);

        return $gap;
    }

    private function close(RouterGap $gap, GapReason $reason, CarbonInterface $endedAt): void
    {
        $gap->forceFill(['ended_at' => $endedAt, 'resolved_at' => now(), 'reason' => $reason])->save();

        $this->markDays($gap);
    }

    private function markDays(RouterGap $gap): void
    {
        $this->affectedDays->markSpans($gap->workspace_id, [[$gap->started_at->getTimestamp(), ($gap->ended_at ?? now())->getTimestamp()]]);
    }

    /** A reboot or a clock jump says more about the cause than plain silence, so they win. */
    private function reasonFor(Router $router, string $bootId, int $clockOffset, CarbonInterface $receivedAt): ?GapReason
    {
        if ($router->boot_id !== null && $router->boot_id !== $bootId) {
            return GapReason::Reboot;
        }

        if ($router->clock_offset_seconds !== null && abs($clockOffset - $router->clock_offset_seconds) > (int) config('network.gaps.clock_jump_seconds')) {
            return GapReason::ClockJump;
        }

        $silentSeconds = $receivedAt->getTimestamp() - $router->last_seen_at->getTimestamp();

        return $silentSeconds > $this->thresholdMinutes() * 60 ? GapReason::NoSync : null;
    }

    private function thresholdMinutes(): int
    {
        return (int) config('network.gaps.threshold_minutes');
    }
}
