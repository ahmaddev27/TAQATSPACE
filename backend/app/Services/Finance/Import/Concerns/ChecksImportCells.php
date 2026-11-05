<?php

declare(strict_types=1);

namespace App\Services\Finance\Import\Concerns;

/**
 * Shared cell checks for the importers. Each check returns an error array for
 * the row and column, or null when the cell is fine.
 */
trait ChecksImportCells
{
    /** @return array{row: int, column: string|null, message: string} */
    private function error(int $row, string $column, string $key, array $replace = []): array
    {
        return ['row' => $row, 'column' => $column, 'message' => __('messages.'.$key, $replace)];
    }

    /** @return array{row: int, column: string|null, message: string}|null */
    private function checkText(int $row, string $column, string $value, int $max): ?array
    {
        if ($value === '') {
            return $this->error($row, $column, 'import_row_required');
        }

        return mb_strlen($value) > $max ? $this->error($row, $column, 'import_row_too_long', ['max' => $max]) : null;
    }

    /** @return array{row: int, column: string|null, message: string}|null */
    private function checkAmount(int $row, string $column, string $value, bool $positive): ?array
    {
        if (preg_match('/^\d{1,10}(\.\d{1,2})?$/', $value) !== 1) {
            return $this->error($row, $column, 'import_row_invalid_amount');
        }

        return $positive && (float) $value <= 0 ? $this->error($row, $column, 'import_row_must_be_positive') : null;
    }

    /**
     * @param  list<array{row: int, values: array<string, string>}>  $rows
     * @return list<string>
     */
    private function column(array $rows, string $name): array
    {
        $values = array_map(static fn (array $row): string => $row['values'][$name], $rows);

        return array_values(array_unique(array_filter($values, static fn (string $value): bool => $value !== '')));
    }

    /**
     * The first row that used each (lower-cased) key, to flag repeats inside one file.
     *
     * @param  list<array{row: int, values: array<string, string>}>  $rows
     * @return list<array{row: int, column: string|null, message: string}>
     */
    private function duplicatesInFile(array $rows, string $column): array
    {
        $errors = [];
        $firstSeen = [];

        foreach ($rows as $row) {
            $key = strtolower($row['values'][$column] ?? '');
            if ($key === '') {
                continue;
            }
            if (isset($firstSeen[$key])) {
                $errors[] = $this->error($row['row'], $column, 'import_row_duplicate_in_file', ['row' => $firstSeen[$key]]);

                continue;
            }
            $firstSeen[$key] = $row['row'];
        }

        return $errors;
    }
}
