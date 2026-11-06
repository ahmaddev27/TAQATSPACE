<?php

declare(strict_types=1);

namespace App\Services\Workspace\Reset\Steps;

use App\Contracts\WorkspaceResetStep;
use App\Models\Subscription;
use App\Models\Workspace;
use App\Services\Hotspot\HotspotAccountService;
use Illuminate\Support\Facades\DB;

/**
 * Hotspot accounts and everything recorded from the routers. The routers stay:
 * they are configuration. Must run before the members step, which deletes the
 * subscriptions the accounts hang off, so that members lose their internet on
 * the router too instead of keeping a login nothing tracks any more.
 */
final class NetworkResetStep implements WorkspaceResetStep
{
    /** Tables holding recorded data, with a `workspace_id`. Commands are handled apart. */
    private const WORKSPACE_TABLES = [
        'attendance_daily',
        'attendance_affected_days',
        'stats_daily_workspace',
        'stats_monthly_subscriber',
        'hotspot_sessions',
        'device_presence',
        'devices',
        'hotspot_events',
        'router_gaps',
    ];

    /** Tables holding recorded data, reached through the router. */
    private const ROUTER_TABLES = ['router_snapshots', 'router_api_logs'];

    public function __construct(private readonly HotspotAccountService $accounts) {}

    public function key(): string
    {
        return 'network';
    }

    public function reset(Workspace $workspace): void
    {
        // Old commands go first: disabling the accounts queues new ones that must reach the router.
        DB::table('router_commands')->where('workspace_id', $workspace->id)->delete();

        $this->accounts->suspendMany(Subscription::query()->where('workspace_id', $workspace->id)->get());
        DB::table('hotspot_accounts')->where('workspace_id', $workspace->id)->delete();

        foreach (self::WORKSPACE_TABLES as $table) {
            DB::table($table)->where('workspace_id', $workspace->id)->delete();
        }

        $routerIds = DB::table('routers')->where('workspace_id', $workspace->id)->pluck('id');

        foreach (self::ROUTER_TABLES as $table) {
            DB::table($table)->whereIn('router_id', $routerIds)->delete();
        }
    }

    public function filePaths(Workspace $workspace): array
    {
        return [];
    }
}
