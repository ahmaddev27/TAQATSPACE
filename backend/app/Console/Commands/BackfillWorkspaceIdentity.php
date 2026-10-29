<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Workspace;
use App\Services\WorkspaceIdentityGenerator;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class BackfillWorkspaceIdentity extends Command
{
    /** @var string */
    protected $signature = 'workspaces:backfill-identity';

    /** @var string */
    protected $description = 'Assign a slug and code to every workspace that lacks one (idempotent).';

    public function handle(WorkspaceIdentityGenerator $generator): int
    {
        $updated = 0;

        Workspace::withTrashed()
            ->where(fn (Builder $query) => $query->whereNull('slug')->orWhereNull('code'))
            ->each(function (Workspace $workspace) use ($generator, &$updated): void {
                $workspace->forceFill([
                    'slug' => $workspace->slug ?? $generator->generateSlug($workspace->name),
                    'code' => $workspace->code ?? $generator->generateCode(),
                ])->save();
                $updated++;
            });

        $this->info("Backfilled identity for {$updated} workspace(s).");

        return self::SUCCESS;
    }
}
