<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A rejected import file or batch. Renders itself as the standard API error
 * envelope so controllers stay free of try/catch.
 */
class ImportException extends RuntimeException
{
    /** @var list<array{row: int, column: string|null, message: string}> */
    private array $rowErrors = [];

    /** @param  array<string, mixed>  $replace */
    private static function make(string $key, array $replace = [], int $status = 422): self
    {
        return new self(__('messages.'.$key, $replace), $status);
    }

    public static function tooLarge(int $megabytes): self
    {
        return self::make('import_file_too_large', ['max' => $megabytes]);
    }

    public static function tooManyRows(int $max): self
    {
        return self::make('import_too_many_rows', ['max' => $max]);
    }

    public static function tooManyColumns(int $max): self
    {
        return self::make('import_too_many_columns', ['max' => $max]);
    }

    public static function formula(): self
    {
        return self::make('import_formula_rejected');
    }

    public static function notXlsx(): self
    {
        return self::make('import_not_xlsx');
    }

    public static function damaged(): self
    {
        return self::make('import_damaged_file');
    }

    public static function tooComplex(): self
    {
        return self::make('import_file_too_complex');
    }

    public static function singleSheetOnly(): self
    {
        return self::make('import_single_sheet_only');
    }

    public static function missingColumns(string $columns): self
    {
        return self::make('import_missing_columns', ['columns' => $columns]);
    }

    public static function empty(): self
    {
        return self::make('import_empty');
    }

    public static function unsupportedServer(): self
    {
        return self::make('import_unsupported_server', [], 503);
    }

    public static function duplicateFile(): self
    {
        return self::make('import_duplicate_file', [], 409);
    }

    /** @param  list<array{row: int, column: string|null, message: string}>  $rowErrors */
    public static function hasErrors(array $rowErrors = []): self
    {
        $exception = self::make('import_has_errors');
        $exception->rowErrors = $rowErrors;

        return $exception;
    }

    public static function notCommittable(): self
    {
        return self::make('import_not_committable', [], 409);
    }

    public function render(): JsonResponse
    {
        $errors = $this->rowErrors === [] ? null : ['rows' => array_map(
            static fn (array $error): string => $error['row'].($error['column'] !== null ? ' ('.$error['column'].')' : '').': '.$error['message'],
            array_slice($this->rowErrors, 0, 50),
        )];

        return ApiResponse::error($this->getMessage(), $this->getCode() ?: 422, $errors);
    }
}
