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
        $existing = $this->repository->findByHash($workspace, $hash);

        if ($existing?->isCommitted()) {
            throw ImportException::duplicateFile();
        }

        $parsed = $this->reader->read($file->getRealPath());
        $this->assertShape($importer, $parsed);

        $errors = $importer->validate($workspace, $parsed['rows']);
        $path = $this->storePayload($workspace, $hash, $errors === [] ? $parsed['rows'] : null);

        return $this->saveBatch($existing ?? new ImportBatch, [
            'workspace_id' => $workspace->id,
            'type' => $type,
            'file_hash' => $hash,
            'status' => $errors === [] ? ImportStatus::Previewed : ImportStatus::Failed,
            'row_count' => count($parsed['rows']),
            'errors' => $errors,
            'payload_path' => $path,
            'created_by' => $actor->id,
        ]);
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

    /** The XLSX template for an import type, as file bytes. */
    public function template(ImportType $type): string
    {
        $importer = $this->importer($type);

        return $this->writer->build($importer->columns(), $importer->templateRows());
    }

    /** @param  array{headers: list<string>, rows: list<array{row: int, values: array<string, string>}>}  $parsed */
    private function assertShape(RowImporter $importer, array $parsed): void
    {
        $missing = array_diff($importer->columns(), $parsed['headers']);
        if ($missing !== []) {
            throw ImportException::missingColumns(implode(', ', $missing));
        }

        if ($parsed['rows'] === []) {
            throw ImportException::empty();
        }
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
    private function saveBatch(ImportBatch $batch, array $attributes): ImportBatch
    {
        try {
            $batch->forceFill($attributes)->save();
        } catch (UniqueConstraintViolationException) {
            // A concurrent upload of the same file won the race.
            throw ImportException::duplicateFile();
        }

        return $batch;
    }

    /** @param  list<array{row: int, values: array<string, string>}>|null  $rows */
    private function storePayload(Workspace $workspace, string $hash, ?array $rows): ?string
    {
        if ($rows === null) {
            return null;
        }

        $path = 'imports/'.$workspace->id.'/'.$hash.'.json';
        Storage::disk('local')->put($path, json_encode($rows, JSON_THROW_ON_ERROR));

        return $path;
    }

    /** @return list<array{row: int, values: array<string, string>}> */
    private function loadPayload(ImportBatch $batch): array
    {
        $json = Storage::disk('local')->get((string) $batch->payload_path);

        return $json === null ? throw ImportException::notCommittable() : json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    private function deletePayload(ImportBatch $batch): void
    {
        Storage::disk('local')->delete((string) $batch->payload_path);
        $batch->forceFill(['payload_path' => null])->save();
    }
}
