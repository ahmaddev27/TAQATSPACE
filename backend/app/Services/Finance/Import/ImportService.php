<?php

declare(strict_types=1);

namespace App\Services\Finance\Import;

use App\Enums\ImportStatus;
use App\Enums\ImportType;
use App\Exceptions\ImportException;
use App\Models\ImportBatch;
use App\Models\User;
use App\Models\Workspace;
use App\Repositories\Eloquent\ImportRepository;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Two-step, all-or-nothing import: `preview` validates a file without writing
 * anything but the batch record; `commit` re-validates and writes every row in
 * one transaction, so a single bad row leaves the workspace untouched. The file
 * hash is unique per workspace, so the same file can never be committed twice.
 */
class ImportService
{
    public function __construct(
        private readonly XlsxReader $reader,
        private readonly XlsxTemplateWriter $writer,
        private readonly ImportRepository $repository,
        private readonly CatalogItemImporter $catalogItems,
        private readonly WithdrawalImporter $withdrawals,
        private readonly AuditLogger $audit,
    ) {}

    public function importer(ImportType $type): RowImporter
    {
        return match ($type) {
            ImportType::CatalogItems => $this->catalogItems,
            ImportType::Withdrawals => $this->withdrawals,
        };
    }

    public function preview(Workspace $workspace, User $actor, UploadedFile $file, ImportType $type): ImportBatch
    {
        $importer = $this->importer($type);
        $hash = hash_file('sha256', $file->getRealPath());

        // Cheap early exit; the authoritative check repeats under the row lock below.
        if ($this->repository->findByHash($workspace, $hash)?->isCommitted()) {
            throw ImportException::duplicateFile();
        }

        $parsed = $this->reader->read($file->getRealPath());
        $rows = $this->assertShape($importer, $parsed);
        $errors = $importer->validate($workspace, $rows);

        return DB::transaction(fn (): ImportBatch => $this->saveBatch($workspace, $actor, $type, $hash, $rows, $errors));
    }

    /**
     * Reads the existing batch under a lock, so a preview racing a commit either
     * waits for it and is refused, or finishes first; it can never set a
     * committed batch back to "previewed".
     *
     * @param  list<array{row: int, values: array<string, string>, numeric: list<string>}>  $rows
     * @param  list<array{row: int, column: string|null, message: string}>  $errors
     */
    private function saveBatch(Workspace $workspace, User $actor, ImportType $type, string $hash, array $rows, array $errors): ImportBatch
    {
        $existing = $this->repository->findByHash($workspace, $hash, lock: true);
        if ($existing?->isCommitted()) {
            throw ImportException::duplicateFile();
        }

        $previousPath = $existing?->payload_path;
        $batch = $this->persistBatch($existing ?? new ImportBatch, [
            'workspace_id' => $workspace->id,
            'type' => $type,
            'file_hash' => $hash,
            'status' => $errors === [] ? ImportStatus::Previewed : ImportStatus::Failed,
            'row_count' => count($rows),
            'errors' => $errors,
            'payload_path' => $errors === [] ? $this->payloadPath($workspace, $hash) : null,
            'created_by' => $actor->id,
        ]);

        $this->syncPayload($batch, $previousPath, $rows);

        return $batch;
    }

    public function commit(Workspace $workspace, User $actor, string $batchId): ImportBatch
    {
        /** @var list<array{row: int, column: string|null, message: string}> $errors */
        $errors = [];

        $batch = DB::transaction(function () use ($workspace, $actor, $batchId, &$errors): ImportBatch {
            $batch = $this->repository->findBatch($workspace, $batchId, lock: true);
            $this->assertCommittable($batch);

            $rows = $this->loadPayload($batch);
            $importer = $this->importer($batch->type);
            $errors = $importer->validate($workspace, $rows);

            if ($errors !== []) {
                // The data changed since the preview: keep the new findings and write nothing else.
                $batch->forceFill(['status' => ImportStatus::Failed, 'errors' => $errors])->save();

                return $batch;
            }

            $importer->apply($workspace, $actor, $rows);
            $batch->forceFill(['status' => ImportStatus::Committed, 'errors' => [], 'committed_at' => now()])->save();
            $this->audit->log('finance.import.committed', $batch, actor: $actor, changes: [
                'type' => $batch->type->value,
                'row_count' => $batch->row_count,
            ]);

            return $batch;
        });

        if ($errors !== []) {
            throw ImportException::hasErrors($errors);
        }

        $this->deletePayload($batch);

        return $batch;
    }

    /**
     * Removes previews nobody committed: their parked rows hold customer emails
     * and must not outlive the upload. Returns how many were removed.
     */
    public function pruneAbandonedPreviews(int $olderThanDays): int
    {
        $pruned = 0;

        $this->repository->eachAbandonedPreview(now()->subDays($olderThanDays), function (ImportBatch $batch) use (&$pruned): void {
            if ($this->repository->deleteIfStillPreviewed($batch)) {
                $this->deletePayloadFile($batch);
                $pruned++;
            }
        });

        return $pruned;
    }

    /** The XLSX template for an import type, as file bytes. */
    public function template(ImportType $type): string
    {
        $importer = $this->importer($type);

        return $this->writer->build($importer->columns(), $importer->templateRows());
    }

    /**
     * Checks the header row and returns only the rows that hold something in a
     * known column, reduced to those columns: a stray note elsewhere on the
     * sheet is not a row of "required" errors, and unrelated cells (which can
     * hold personal data) are never parked on disk.
     *
     * @param  array{headers: list<string>, rows: list<array{row: int, values: array<string, string>, numeric: list<string>}>}  $parsed
     * @return list<array{row: int, values: array<string, string>, numeric: list<string>}>
     */
    private function assertShape(RowImporter $importer, array $parsed): array
    {
        $missing = array_diff($importer->columns(), $parsed['headers']);
        if ($missing !== []) {
            throw ImportException::missingColumns(implode(', ', $missing));
        }

        $known = array_flip($importer->columns());
        $rows = [];
        foreach ($parsed['rows'] as $row) {
            $values = array_intersect_key($row['values'], $known);
            if (implode('', $values) !== '') {
                $rows[] = ['row' => $row['row'], 'values' => $values, 'numeric' => array_values(array_intersect($row['numeric'], $importer->columns()))];
            }
        }

        return $rows === [] ? throw ImportException::empty() : $rows;
    }

    private function assertCommittable(ImportBatch $batch): void
    {
        if ($batch->isCommitted()) {
            throw ImportException::duplicateFile();
        }

        if ($batch->status === ImportStatus::Failed || $batch->payload_path === null) {
            throw ImportException::hasErrors($batch->errors ?? []);
        }
    }

    /** @param  array<string, mixed>  $attributes */
    private function persistBatch(ImportBatch $batch, array $attributes): ImportBatch
    {
        try {
            $batch->forceFill($attributes)->save();
        } catch (UniqueConstraintViolationException) {
            // A concurrent upload of the same file won the race.
            throw ImportException::duplicateFile();
        }

        return $batch;
    }

    private function payloadPath(Workspace $workspace, string $hash): string
    {
        return 'imports/'.$workspace->id.'/'.$hash.'.json';
    }

    /**
     * Parks the validated rows for the commit, or removes a file an earlier
     * preview of the same upload left behind once this one has errors.
     *
     * @param  list<array{row: int, values: array<string, string>, numeric: list<string>}>  $rows
     */
    private function syncPayload(ImportBatch $batch, ?string $previousPath, array $rows): void
    {
        if ($batch->payload_path !== null) {
            Storage::disk('local')->put($batch->payload_path, json_encode($rows, JSON_THROW_ON_ERROR));
        } elseif ($previousPath !== null) {
            Storage::disk('local')->delete($previousPath);
        }
    }

    /** @return list<array{row: int, values: array<string, string>, numeric?: list<string>}> */
    private function loadPayload(ImportBatch $batch): array
    {
        $json = Storage::disk('local')->get((string) $batch->payload_path);

        return $json === null ? throw ImportException::notCommittable() : json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    private function deletePayload(ImportBatch $batch): void
    {
        $this->deletePayloadFile($batch);
        $batch->forceFill(['payload_path' => null])->save();
    }

    private function deletePayloadFile(ImportBatch $batch): void
    {
        if ($batch->payload_path !== null) {
            Storage::disk('local')->delete($batch->payload_path);
        }
    }
}
