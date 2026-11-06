<?php

declare(strict_types=1);

namespace App\Services\Demo\Steps;

use App\Enums\GapReason;
use App\Jobs\ProcessRouterBatch;
use App\Models\Router;
use App\Models\Subscription;
use App\Models\Workspace;
use App\Services\Demo\DemoContext;
use App\Services\Demo\DemoSeedStep;
use App\Services\Hotspot\HotspotAccountService;
use App\Services\Hotspot\HotspotSyncService;
use App\Services\Hotspot\SyncPayload;
use App\Services\Hotspot\SyncWireParser;
use App\Services\Network\AttendanceRebuilder;
use App\Services\Network\ScoringService;
use App\Services\Network\GapDetector;
use App\Services\Network\StatsAggregator;
use App\Services\Router\RouterService;
use App\Support\Network\LocalDays;
use App\Support\Router\RouterKeyHasher;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

/**
 * The first tenant's network: hotspot logins for two subscribers and two routers.
 * The first router is the one the integration page shows and the install,
 * script-update and diagnostics checks use; its key is never needed. The second
 * is the sync probe: its key is derived from the demo password, so a check can
 * post to the real sync endpoint without anyone storing or printing a key.
 *
 * Data goes through the real services (accounts, the sync handler with the same
 * text the router scripts send, the batch processor), not through table writes.
 */
class NetworkStep implements DemoSeedStep
{
    public const MAIN_ROUTER = 'Main router';

    public const PROBE_ROUTER = 'Sync probe router';

    /** Older than the shipped scripts, so the script-update check has something to offer. */
    private const OLD_SCRIPT_VERSION = '1.0.0';

    private const CURRENT_SCRIPT_VERSION = '1.1.0';

    /** @var array<string, array{mac: string, ip: string}> subscriber key => device */
    private const DEVICES = [
        'freelancer1' => ['mac' => 'AA:BB:CC:00:00:01', 'ip' => '10.10.0.11'],
        'freelancer2' => ['mac' => 'AA:BB:CC:00:00:02', 'ip' => '10.10.0.12'],
    ];

    public function __construct(
        private readonly RouterService $routers,
        private readonly HotspotAccountService $accounts,
        private readonly HotspotSyncService $sync,
        private readonly SyncWireParser $parser,
        private readonly AttendanceRebuilder $attendance,
        private readonly StatsAggregator $stats,
        private readonly ScoringService $scoring,
        private readonly GapDetector $gaps,
        private readonly LocalDays $days = new LocalDays,
    ) {}

    public function seed(DemoContext $context): void
    {
        $workspace = $context->workspace('ws1');
        $main = $this->createRouters($workspace, $context);

        $usernames = $this->provisionAccounts($context);
        $this->pushBatch($main, $this->mainBatch($usernames));
        $this->pushBatch($this->probeRouter($workspace), $this->probeBatch());
        $this->recordGap($main);

        ProcessRouterBatch::dispatchSync((string) $main->getKey());
        $this->attendance->rebuildAffected();
        $this->aggregateStats($workspace);
    }

    /** One closed gap inside the sessions' hours, so today shows as a day with missing data. The table cascades with the workspace. */
    private function recordGap(Router $router): void
    {
        $this->gaps->record($router, GapReason::NoSync, Date::now('UTC')->subMinutes(90), Date::now('UTC')->subMinutes(75));
    }

    /** The stats tables cascade with the workspace, so the cleaner needs no step of its own for them. */
    private function aggregateStats(Workspace $workspace): void
    {
        $today = $this->days->dateOf(Date::now()->getTimestamp(), $this->days->zone($workspace->timezone));

        $this->stats->aggregate($workspace, Date::parse($today)->subDay()->toDateString(), $today);
        // subscriber_scores cascade with the workspace and the users like the stats tables.
        $this->scoring->compute($workspace);
    }

    private function createRouters(Workspace $workspace, DemoContext $context): Router
    {
        $main = $this->routers->create($workspace, self::MAIN_ROUTER)->router;
        // The page shows the first router by creation time; make the order explicit, not a same-second tie.
        $main->forceFill(['created_at' => now()->subMinutes(5), 'script_version' => self::OLD_SCRIPT_VERSION])->save();

        $probe = $this->routers->create($workspace, self::PROBE_ROUTER)->router;
        $probe->update(['key_hash' => RouterKeyHasher::hash($context->routerKey)]);

        return $main;
    }

    private function probeRouter(Workspace $workspace): Router
    {
        return Router::query()->withoutWorkspaceScope()
            ->where('workspace_id', $workspace->getKey())
            ->where('name', self::PROBE_ROUTER)
            ->firstOrFail();
    }

    /** @return array<string, string> subscriber key => hotspot username */
    private function provisionAccounts(DemoContext $context): array
    {
        $subscriptions = Subscription::query()
            ->whereIn('member_id', collect(array_keys(self::DEVICES))->map(fn (string $key): string => $context->user($key)->id)->all())
            ->get()
            ->keyBy('member_id');
        $usernames = [];

        foreach (array_keys(self::DEVICES) as $key) {
            $usernames[$key] = $this->accounts
                ->provision($subscriptions[$context->user($key)->id], Str::password(16, symbols: false))
                ->username;
        }

        return $usernames;
    }

    /**
     * Two logins, one logout and a snapshot with both subscribers online, written as the router scripts do.
     *
     * @param  array<string, string>  $usernames
     */
    private function mainBatch(array $usernames): string
    {
        $one = self::DEVICES['freelancer1'];
        $two = self::DEVICES['freelancer2'];
        $user1 = $usernames['freelancer1'];
        $user2 = $usernames['freelancer2'];

        return implode("\n", [
            $this->header('demo-main-1', 1, self::OLD_SCRIPT_VERSION, 'DEMO-SN-0001', '7.14'),
            "E|1|login|{$this->ago(240)}|{$user1}|{$one['mac']}|{$one['ip']}|||",
            "E|2|logout|{$this->ago(180)}|{$user1}|{$one['mac']}|{$one['ip']}|1h|52428800|10485760",
            "E|3|login|{$this->ago(120)}|{$user2}|{$two['mac']}|{$two['ip']}|||",
            "E|4|login|{$this->ago(60)}|{$user1}|{$one['mac']}|{$one['ip']}|||",
            'S|demo-main-snap-1|0|1',
            "{$user1}|{$one['mac']}|{$one['ip']}|1h|1048576|524288",
            "{$user2}|{$two['mac']}|{$two['ip']}|2h|2097152|1048576",
        ])."\n";
    }

    private function probeBatch(): string
    {
        return $this->header('demo-probe-1', 1, self::CURRENT_SCRIPT_VERSION)."\n";
    }

    private function header(string $batchId, int $seq, string $scriptVersion, string $serial = '', string $routerOs = ''): string
    {
        $now = Date::now('UTC')->format('Y-m-d H:i:s');
        $bootId = Str::replace('-', '', $batchId).'boot';

        return "H|{$batchId}|{$seq}|{$bootId}|{$now}|full|demonce{$seq}|{$scriptVersion}|{$serial}|{$routerOs}";
    }

    private function ago(int $minutes): string
    {
        return Date::now('UTC')->subMinutes($minutes)->format('Y-m-d H:i:s');
    }

    private function pushBatch(Router $router, string $wire): void
    {
        $this->sync->handle($router, SyncPayload::fromValidated($this->parser->parse($wire)));
    }
}
