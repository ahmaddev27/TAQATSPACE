<?php

declare(strict_types=1);

namespace App\Services\Workspace\Reset\Steps;

use App\Contracts\WorkspaceResetStep;
use App\Models\Workspace;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Receipts with their allocations, and unbilled charges. Must run before the
 * invoices step: deleting an invoice only removes its allocations, which would
 * leave the receipt looking like unspent customer credit. Import batches go too,
 * so a withdrawals file can be imported again: the charges it created are gone.
 * A catalog file cannot, because the catalog it created stays and its codes
 * would be rejected as existing. The catalog, plans and document numbering are
 * configuration and stay.
 */
final class FinanceResetStep implements WorkspaceResetStep
{
    public function key(): string
    {
        return 'finance';
    }

    public function reset(Workspace $workspace): void
    {
        $this->deletePrivateProofsAfterCommit($workspace);
        $this->deleteParkedImportsAfterCommit($workspace);

        DB::table('receipts')->where('workspace_id', $workspace->id)->delete();
        DB::table('unbilled_charges')->where('workspace_id', $workspace->id)->delete();
        DB::table('import_batches')->where('workspace_id', $workspace->id)->delete();
    }

    public function filePaths(Workspace $workspace): array
    {
        return $this->proofs($workspace)
            ->where('receipt_disk', $this->mediaDisk())
            ->pluck('receipt_path')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The registry only deletes files on the media disk, and proofs of new
     * receipts are private, so those are removed here once the data is gone.
     */
    private function deletePrivateProofsAfterCommit(Workspace $workspace): void
    {
        $byDisk = $this->proofs($workspace)
            ->where('receipt_disk', '!=', $this->mediaDisk())
            ->groupBy('receipt_disk');

        DB::afterCommit(function () use ($byDisk): void {
            foreach ($byDisk as $disk => $proofs) {
                try {
                    Storage::disk((string) $disk)->delete($proofs->pluck('receipt_path')->all());
                } catch (Throwable $e) {
                    // The data is already gone; a leftover file must not fail the request.
                    Log::warning('Workspace reset could not delete some receipt proofs.', ['error' => $e->getMessage()]);
                }
            }
        });
    }

    /** Rows of a previewed file wait on the private disk until it is committed. */
    private function deleteParkedImportsAfterCommit(Workspace $workspace): void
    {
        $paths = DB::table('import_batches')
            ->where('workspace_id', $workspace->id)
            ->whereNotNull('payload_path')
            ->pluck('payload_path')
            ->all();

        if ($paths === []) {
            return;
        }

        DB::afterCommit(static function () use ($paths): void {
            try {
                Storage::disk('local')->delete($paths);
            } catch (Throwable $e) {
                // The data is already gone; a leftover file must not fail the request.
                Log::warning('Workspace reset could not delete some parked import files.', ['error' => $e->getMessage()]);
            }
        });
    }

    /** @return Collection<int, object> */
    private function proofs(Workspace $workspace): Collection
    {
        return DB::table('receipts')
            ->where('workspace_id', $workspace->id)
            ->whereNotNull('receipt_path')
            ->get(['receipt_path', 'receipt_disk'])
            ->map(function (object $proof): object {
                $proof->receipt_disk ??= $this->mediaDisk();

                return $proof;
            });
    }

    private function mediaDisk(): string
    {
        return (string) config('filesystems.media', 'public');
    }
}
