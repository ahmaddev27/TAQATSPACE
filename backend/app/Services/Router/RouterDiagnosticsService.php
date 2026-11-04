<?php

declare(strict_types=1);

namespace App\Services\Router;

use App\Enums\DiagnosticStatus;
use App\Enums\RouterCommandStatus;
use App\Enums\RouterStatus;
use App\Models\HotspotEvent;
use App\Models\Router;
use App\Models\RouterCommand;
use App\Repositories\Eloquent\RouterScriptRepository;
use App\Services\Router\Diagnostics\DiagnosticCheck;
use Illuminate\Support\Facades\Cache;

/**
 * Deep health check of one router integration. Each check yields pass,
 * warning or fail with a human reason. The result is cached briefly so a
 * button mashed by the owner cannot turn into a query storm.
 */
class RouterDiagnosticsService
{
    public const CACHE_SECONDS = 15;

    private const WARN_SYNC_MINUTES = Router::ONLINE_WINDOW_MINUTES;

    private const FAIL_SYNC_MINUTES = 15;

    private const WARN_DRIFT_SECONDS = 60;

    private const FAIL_DRIFT_SECONDS = 300;

    private const STUCK_COMMAND_MINUTES = 10;

    private const FAIL_STUCK_COMMAND_MINUTES = 60;

    /** How many recent events are scanned for sequence gaps. */
    private const SEQ_WINDOW = 500;

    public function __construct(private readonly RouterScriptRepository $scripts) {}

    /** @return list<DiagnosticCheck> */
    public function run(Router $router): array
    {
        $key = sprintf('router-diagnostics:%s:%s', $router->getKey(), app()->getLocale());

        $checks = Cache::remember($key, self::CACHE_SECONDS, fn (): array => array_map(
            fn (DiagnosticCheck $check): array => $check->toArray(),
            $this->evaluate($router),
        ));

        return array_map(fn (array $c): DiagnosticCheck => new DiagnosticCheck($c['name'], DiagnosticStatus::from($c['status']), $c['detail']), $checks);
    }

    /** @return list<DiagnosticCheck> */
    private function evaluate(Router $router): array
    {
        return [
            $this->lastSync($router),
            $this->clockDrift($router),
            $this->seqGaps($router),
            $this->stuckCommands($router),
            $this->scriptVersion($router),
            $this->serialConsistency($router),
        ];
    }

    private function lastSync(Router $router): DiagnosticCheck
    {
        if ($router->last_seen_at === null) {
            return $this->check('last_sync', DiagnosticStatus::Fail, __('messages.diag_never_synced'));
        }

        $minutes = (int) $router->last_seen_at->diffInMinutes(now(), true);
        $status = match (true) {
            $minutes >= self::FAIL_SYNC_MINUTES => DiagnosticStatus::Fail,
            $minutes >= self::WARN_SYNC_MINUTES => DiagnosticStatus::Warning,
            default => DiagnosticStatus::Pass,
        };

        $detail = $status === DiagnosticStatus::Pass ? __('messages.diag_sync_recent') : __('messages.diag_sync_stale', ['minutes' => $minutes]);

        return $this->check('last_sync', $status, $detail);
    }

    private function clockDrift(Router $router): DiagnosticCheck
    {
        if ($router->clock_offset_seconds === null) {
            return $this->check('clock_drift', DiagnosticStatus::Warning, __('messages.diag_drift_unknown'));
        }

        $drift = abs($router->clock_offset_seconds);
        $status = match (true) {
            $drift > self::FAIL_DRIFT_SECONDS => DiagnosticStatus::Fail,
            $drift > self::WARN_DRIFT_SECONDS => DiagnosticStatus::Warning,
            default => DiagnosticStatus::Pass,
        };

        $detail = $status === DiagnosticStatus::Pass ? __('messages.diag_drift_ok', ['seconds' => $drift]) : __('messages.diag_drift_high', ['seconds' => $drift]);

        return $this->check('clock_drift', $status, $detail);
    }

    private function seqGaps(Router $router): DiagnosticCheck
    {
        $gaps = $this->countSeqGaps($router);

        return $gaps === 0
            ? $this->check('seq_gaps', DiagnosticStatus::Pass, __('messages.diag_seq_ok'))
            : $this->check('seq_gaps', DiagnosticStatus::Warning, __('messages.diag_seq_gaps', ['count' => $gaps]));
    }

    /** Gaps inside the latest boot's recent event window; older boots restart their counter, so they are not compared. */
    private function countSeqGaps(Router $router): int
    {
        $uids = HotspotEvent::query()
            ->where('router_id', $router->getKey())
            ->orderByDesc('id')
            ->limit(self::SEQ_WINDOW)
            ->pluck('event_uid');

        if ($uids->isEmpty()) {
            return 0;
        }

        $boot = substr((string) $uids->first(), 0, (int) strrpos((string) $uids->first(), '-'));
        $seqs = $uids
            ->filter(fn ($uid): bool => str_starts_with((string) $uid, $boot.'-'))
            ->map(fn ($uid): int => (int) substr((string) $uid, strlen($boot) + 1))
            ->unique();

        return max(0, $seqs->max() - $seqs->min() + 1 - $seqs->count());
    }

    private function stuckCommands(Router $router): DiagnosticCheck
    {
        $stuck = RouterCommand::query()
            ->withoutWorkspaceScope()
            ->where('router_id', $router->getKey())
            ->whereIn('status', RouterCommandStatus::openValues())
            ->where('created_at', '<=', now()->subMinutes(self::STUCK_COMMAND_MINUTES))
            ->selectRaw('count(*) as total, min(created_at) as oldest')
            ->first();

        $total = (int) ($stuck?->total ?? 0);

        if ($total === 0) {
            return $this->check('stuck_commands', DiagnosticStatus::Pass, __('messages.diag_commands_ok'));
        }

        $oldestIsOld = now()->parse((string) $stuck->oldest)->lte(now()->subMinutes(self::FAIL_STUCK_COMMAND_MINUTES));

        return $this->check(
            'stuck_commands',
            $oldestIsOld ? DiagnosticStatus::Fail : DiagnosticStatus::Warning,
            __('messages.diag_commands_stuck', ['count' => $total]),
        );
    }

    private function scriptVersion(Router $router): DiagnosticCheck
    {
        $current = $router->script_version;
        $latest = $this->scripts->version();

        if ($current === null) {
            return $this->check('script_version', DiagnosticStatus::Warning, __('messages.diag_script_unknown'));
        }

        return version_compare($current, $latest, '<')
            ? $this->check('script_version', DiagnosticStatus::Warning, __('messages.diag_script_outdated', ['current' => $current, 'latest' => $latest]))
            : $this->check('script_version', DiagnosticStatus::Pass, __('messages.diag_script_ok', ['version' => $current]));
    }

    private function serialConsistency(Router $router): DiagnosticCheck
    {
        return match (true) {
            $router->status === RouterStatus::Locked => $this->check('serial', DiagnosticStatus::Fail, __('messages.diag_serial_locked')),
            $router->serial_number === null => $this->check('serial', DiagnosticStatus::Warning, __('messages.diag_serial_unbound')),
            default => $this->check('serial', DiagnosticStatus::Pass, __('messages.diag_serial_ok')),
        };
    }

    private function check(string $name, DiagnosticStatus $status, string $detail): DiagnosticCheck
    {
        return new DiagnosticCheck($name, $status, $detail);
    }
}
