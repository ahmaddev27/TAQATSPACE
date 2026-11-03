<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Enums\CatalogKind;
use Illuminate\Validation\Rule;

class StoreCatalogCategoryRequest extends CatalogRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(CatalogKind::class)],
            'code' => [
                ...$this->codeRules(),
                Rule::unique('catalog_categories', 'code')->where('workspace_id', $this->workspaceId()),
            ],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ];
    }
}
