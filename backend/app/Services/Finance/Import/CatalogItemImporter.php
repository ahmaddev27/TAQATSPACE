<?php

declare(strict_types=1);

namespace App\Services\Finance\Import;

use App\Enums\CatalogUnit;
use App\Enums\ImportType;
use App\Models\User;
use App\Models\Workspace;
use App\Repositories\Eloquent\ImportRepository;
use App\Services\Finance\CatalogItemService;
use App\Services\Finance\Import\Concerns\ChecksImportCells;

/**
 * Catalog items keyed by `code`. A code that already exists (archived or not)
 * is rejected, never updated.
 */
class CatalogItemImporter implements RowImporter
{
    use ChecksImportCells;

    public function __construct(
        private readonly ImportRepository $repository,
        private readonly CatalogItemService $items,
    ) {}

    public function type(): ImportType
    {
        return ImportType::CatalogItems;
    }

    public function columns(): array
    {
        return ['code', 'category_code', 'name_ar', 'name_en', 'unit', 'price'];
    }

    public function templateRows(): array
    {
        return [['COFFEE-01', 'DRINKS', 'قهوة', 'Coffee', 'piece', '6.50']];
    }

    public function validate(Workspace $workspace, array $rows): array
    {
        $existing = $this->repository->existingItemCodes($workspace, $this->column($rows, 'code'));
        $categories = $this->repository->activeCategoryIds($workspace, $this->column($rows, 'category_code'));
        $errors = $this->duplicatesInFile($rows, 'code');

        foreach ($rows as $row) {
            array_push($errors, ...$this->rowErrors($row, $existing, $categories));
        }

        usort($errors, static fn (array $a, array $b): int => $a['row'] <=> $b['row']);

        return $errors;
    }

    public function apply(Workspace $workspace, User $actor, array $rows): int
    {
        $categories = $this->repository->activeCategoryIds($workspace, $this->column($rows, 'category_code'));

        foreach ($rows as $row) {
            $values = $row['values'];
            $this->items->create($workspace, [
                'catalog_category_id' => $categories[strtolower($values['category_code'])],
                'code' => $values['code'],
                'name_ar' => $values['name_ar'],
                'name_en' => $values['name_en'],
                'unit' => strtolower($values['unit']),
                'price' => $values['price'],
            ], $actor);
        }

        return count($rows);
    }

    /**
     * @param  array{row: int, values: array<string, string>}  $row
     * @param  array<string, true>  $existing
     * @param  array<string, string>  $categories
     * @return list<array{row: int, column: string|null, message: string}>
     */
    private function rowErrors(array $row, array $existing, array $categories): array
    {
        $number = $row['row'];
        $values = $row['values'];

        return array_values(array_filter([
            $this->codeError($number, $values['code'], $existing),
            $this->checkText($number, 'category_code', $values['category_code'], 50)
                ?? (isset($categories[strtolower($values['category_code'])]) ? null : $this->error($number, 'category_code', 'import_row_unknown_category')),
            $this->checkText($number, 'name_ar', $values['name_ar'], 255),
            $this->checkText($number, 'name_en', $values['name_en'], 255),
            CatalogUnit::tryFrom(strtolower($values['unit'])) === null ? $this->error($number, 'unit', 'import_row_invalid_unit', ['units' => implode(', ', CatalogUnit::values())]) : null,
            $this->checkAmount($number, 'price', $values['price'], false),
        ]));
    }

    /**
     * @param  array<string, true>  $existing
     * @return array{row: int, column: string|null, message: string}|null
     */
    private function codeError(int $row, string $code, array $existing): ?array
    {
        if ($code === '' || mb_strlen($code) > 50) {
            return $this->checkText($row, 'code', $code, 50);
        }

        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/', $code) !== 1) {
            return $this->error($row, 'code', 'import_row_invalid_code');
        }

        return isset($existing[strtolower($code)]) ? $this->error($row, 'code', 'import_row_code_exists') : null;
    }
}
