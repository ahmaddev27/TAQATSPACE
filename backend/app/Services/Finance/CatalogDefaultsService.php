<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\CatalogKind;
use App\Enums\ExpenseCategory;
use App\Models\CatalogCategory;
use App\Models\Workspace;
use Illuminate\Support\Str;

/**
 * Default catalog data a workspace gets when Finance v2 is switched on: the
 * built-in expense categories, so expenses can move off the hard-coded list.
 * Safe to run repeatedly: categories that already exist are left alone.
 */
class CatalogDefaultsService
{
    /** @var array<string, array{0: string, 1: string}> value => [Arabic, English] */
    private const EXPENSE_NAMES = [
        'rent' => ['الإيجار', 'Rent'],
        'utilities' => ['المرافق', 'Utilities'],
        'salaries' => ['الرواتب', 'Salaries'],
        'maintenance' => ['الصيانة', 'Maintenance'],
        'supplies' => ['المستلزمات', 'Supplies'],
        'marketing' => ['التسويق', 'Marketing'],
        'other' => ['أخرى', 'Other'],
    ];

    public function seedExpenseCategories(Workspace $workspace): void
    {
        $existing = CatalogCategory::query()->withoutWorkspaceScope()
            ->where('workspace_id', $workspace->id)->pluck('code')->all();
        $nextSort = (int) CatalogCategory::query()->withoutWorkspaceScope()
            ->where('workspace_id', $workspace->id)->max('sort_order');
        $now = now();
        $rows = [];

        foreach (ExpenseCategory::cases() as $case) {
            $code = 'EXP-'.Str::upper($case->value);

            if (in_array($code, $existing, true)) {
                continue;
            }

            [$nameAr, $nameEn] = self::EXPENSE_NAMES[$case->value];
            $rows[] = [
                'id' => (string) Str::uuid(),
                'workspace_id' => $workspace->id,
                'kind' => CatalogKind::Expense->value,
                'code' => $code,
                'name_ar' => $nameAr,
                'name_en' => $nameEn,
                'sort_order' => ++$nextSort,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            CatalogCategory::query()->withoutWorkspaceScope()->insert($rows);
        }
    }
}
