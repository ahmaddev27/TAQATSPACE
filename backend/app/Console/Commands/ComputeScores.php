<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\WorkspaceFeature;
use App\Models\Workspace;
use App\Models\WorkspaceFeatureFlag;
use App\Services\Network\ScoringService;
use Illuminate\Console\Command;

/**
 * Recomputes loyalty and strength scores and segments from the stored monthly
 * statistics, for one workspace or for every workspace with the network module.
 * The nightly stats recalculation does the same after it aggregates; this is the
 * way to apply changed weights without waiting for it.
 */
class ComputeScores extends Command
{
    protected $signature = 'scores:compute
        {workspace? : Workspace id; every workspace with the network module when omitted}';

    protected $description = 'Compute subscriber loyalty and strength scores and segments.';

    public function handle(ScoringService $scoring): int
    {
        $workspaces = $this->workspaces();

        if ($workspaces === null) {
            $this->error('Workspace not found.');

            return self::FAILURE;
        }

        foreach ($workspaces as $workspace) {
            $this->info("Workspace {$workspace->getKey()}: {$scoring->compute($workspace)} subscribers scored.");
        }

        return self::SUCCESS;
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
}
