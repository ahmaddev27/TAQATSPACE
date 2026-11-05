<?php

declare(strict_types=1);

namespace App\Services\Finance\Import;

use App\Enums\ImportType;
use App\Models\User;
use App\Models\Workspace;

/**
 * One import type. `validate` reads and writes nothing; `apply` writes every
 * row and runs inside the caller's single transaction.
 *
 * @phpstan-type Row array{row: int, values: array<string, string>}
 * @phpstan-type RowError array{row: int, column: string|null, message: string}
 */
interface RowImporter
{
    public function type(): ImportType;

    /** @return list<string> columns the file must have */
    public function columns(): array;

    /** @return list<list<string>> sample rows for the template */
    public function templateRows(): array;

    /**
     * @param  list<Row>  $rows
     * @return list<RowError>
     */
    public function validate(Workspace $workspace, array $rows): array;

    /**
     * @param  list<Row>  $rows
     * @return int rows written
     */
    public function apply(Workspace $workspace, User $actor, array $rows): int;
}
