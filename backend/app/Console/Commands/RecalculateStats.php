<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\WorkspaceFeature;
use App\Exceptions\RecalculationInProgressException;
use App\Models\Workspace;
use App\Models\WorkspaceFeatureFlag;
use App\Repositories\Eloquent\RouterGapRepository;
use App\Services\Network\StatsRecalculationService;
use App\Support\Network\LocalDays;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Queues a recalculation on the heavy queue for one workspace, or for every
 * workspace with the network module. With an explicit --from/--to it repairs a
 * range (and is audited); without them it is the routine pass over the last
 * two local days, which the nightly schedule runs.
 */
class RecalculateStats extends Command
{
    private const ROUTINE_DAYS = 2;

    // Bounds the nightly work when a router has been dead for months.
    private const MAX_OPEN_GAP_DAYS = 31;

    protected $signature = 'stats:recalculate
        {workspace? : Workspace id; every workspace with the network module when omitted}
        {--from= : First local date, Y-m-d}
        {--to= : Last local date, Y-m-d, inclusive}
        {--now : Run in this process instead of queueing}';

    protected $description = 'Recalculate attendance and statistics for a date range.';

    public function __construct(private readonly RouterGapRepository $gaps, private readonly LocalDays $days = new LocalDays)
    {
        parent::__construct();
    }

    public function handle(StatsRecalculationService $recalculation): int
    {
        $from = $this->option('from');
        $to = $this->option('to');

        if (($from === null) !== ($to === null) || ($from !== null && ! $this->validRange((string) $from, (string) $to))) {
            $this->error('--from and --to must be given together as Y-m-d dates with --from not after --to.');

            return self::INVALID;
        }

        $workspaces = $this->workspaces();

        if ($workspaces === null) {
            $this->error('Workspace not found.');

            return self::FAILURE;
        }

        foreach ($workspaces as $workspace) {
            $this->request($recalculation, $workspace, $from === null ? null : [(string) $from, (string) $to]);
        }

        return self::SUCCESS;
    }

    /** @param array{0: string, 1: string}|null $range explicit range, or null for the routine window */
    private function request(StatsRecalculationService $recalculation, Workspace $workspace, ?array $range): void
    {
        [$from, $to] = $range ?? $this->routineRange($workspace);

        try {
            $recalculation->request($workspace, $from, $to, null, $range !== null, (bool) $this->option('now'));
            $this->info("Workspace {$workspace->getKey()}: {$from} to {$to} ".($this->option('now') ? 'done.' : 'queued.'));
        } catch (RecalculationInProgressException) {
            $this->warn("Workspace {$workspace->getKey()}: a recalculation is already running, skipped.");
        }
    }

    /** @return iterable<Workspace>|null null when the named workspace does not exist */
    private function workspaces(): ?iterable
    {
        if ($this->argument('workspace') !== null) {
            $workspace = Workspace::query()->find($this->argument('workspace'));

            return $workspace === null ? null : [$workspace];
        }

        $enabled = WorkspaceFeatureFlag::query()
            ->where('feature', WorkspaceFeature::Network->value)
            ->where('enabled', true)
            ->select('workspace_id');

        return Workspace::query()->whereIn('id', $enabled)->cursor();
    }

    /**
     * The last two local days, reaching back to where an ongoing gap began: its daily gap minutes
     * grow every night and would otherwise stay as the last recalculation left them.
     *
     * @return array{0: string, 1: string}
     */
    private function routineRange(Workspace $workspace): array
    {
        $zone = $this->days->zone($workspace->timezone);
        $today = $this->days->dateOf(now()->getTimestamp(), $zone);
        $from = CarbonImmutable::parse($today)->subDays(self::ROUTINE_DAYS - 1)->toDateString();
        $gapStart = $this->gaps->oldestOpenStart((string) $workspace->getKey());

        if ($gapStart !== null) {
            $gapFrom = max($this->days->dateOf($gapStart->getTimestamp(), $zone), CarbonImmutable::parse($today)->subDays(self::MAX_OPEN_GAP_DAYS)->toDateString());
            $from = min($from, $gapFrom);
        }

        return [$from, $today];
    }

    private function validRange(string $from, string $to): bool
    {
        return $this->isDate($from) && $this->isDate($to) && $from <= $to;
    }

    private function isDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
