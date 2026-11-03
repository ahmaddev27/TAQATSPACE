<?php

declare(strict_types=1);

namespace App\Services\Workspace\Reset;

use App\Models\User;
use App\Models\Workspace;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Wipes a workspace's operational data while keeping its identity, owner and
 * configuration. All steps share one transaction; files are removed only after
 * it commits, so a rollback never orphans rows that still point at them.
 */
class WorkspaceResetService
{
    public function __construct(
        private readonly WorkspaceResetter $resetter,
        private readonly AuditLogger $audit,
    ) {}

    public function reset(Workspace $workspace, User $actor, string $reason): void
    {
        $paths = DB::transaction(function () use ($workspace, $actor, $reason): array {
            $paths = $this->resetter->reset($workspace);

            $this->audit->log(
                'workspace.reset',
                $workspace,
                $reason,
                ['steps' => $this->resetter->keys()],
                $actor,
                (string) $workspace->id,
            );

            return $paths;
        });

        Cache::forget("workspace:{$workspace->id}:dashboard_stats");
        $this->deleteFiles($paths);
    }

    /** @param  list<string>  $paths */
    private function deleteFiles(array $paths): void
    {
        if ($paths === []) {
            return;
        }

        try {
            Storage::disk((string) config('filesystems.media', 'public'))->delete($paths);
        } catch (Throwable $e) {
            // The data is already gone; a leftover file must not fail the request.
            Log::warning('Workspace reset could not delete some files.', ['error' => $e->getMessage()]);
        }
    }
}
