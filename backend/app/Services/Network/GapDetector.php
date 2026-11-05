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
        $routers = $this->gaps->silentWithoutGap($now->copy()->subMinutes($this->thresholdMinutes()));

        if ($routers->isEmpty()) {
            return 0;
        }

        RouterGap::query()->withoutWorkspaceScope()->insert($routers->map(fn (Router $router): array => [
            'id' => (string) Str::uuid(),
            'router_id' => $router->getKey(),
            'workspace_id' => $router->workspace_id,
            'started_at' => $router->last_seen_at,
            'ended_at' => null,
            'reason' => GapReason::NoSync->value,
            'resolved_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());

        foreach ($routers->groupBy('workspace_id') as $workspaceId => $silent) {
            $this->affectedDays->markSpans((string) $workspaceId, $silent->map(fn (Router $router): array => [$router->last_seen_at->getTimestamp(), $now->getTimestamp()])->all());
        }

        return $routers->count();
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
        $open = $this->gaps->openFor((string) $router->getKey());

        if ($open !== null) {
            $this->close($open, $reason ?? $open->reason, $receivedAt);
        } elseif ($reason !== null) {
            $this->record($router, $reason, $router->last_seen_at, $receivedAt);
        }
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
