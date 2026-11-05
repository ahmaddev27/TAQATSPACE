<?php

declare(strict_types=1);

namespace App\Http\Requests\Owner;

use App\Enums\CatalogKind;
use App\Enums\PaymentMethod;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Validation rules for the Finance v2 expense fields, shared by the store and
 * update requests so the two cannot drift apart.
 */
final class ExpenseRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function v2Fields(?string $workspaceId): array
    {
        return [
            'catalog_category_id' => ['nullable', 'uuid', self::expenseCategoryOf($workspaceId)],
            'method' => ['nullable', Rule::in(PaymentMethod::manualValues())],
            'reference' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** A live expense-kind category of the owner's own workspace. */
    private static function expenseCategoryOf(?string $workspaceId): Exists
    {
        return Rule::exists('catalog_categories', 'id')
            ->where('workspace_id', $workspaceId ?? '')
            ->where('kind', CatalogKind::Expense->value)
            ->whereNull('archived_at');
    }
}
