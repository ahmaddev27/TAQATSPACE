<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Enums\CatalogUnit;
use Illuminate\Validation\Rule;

class StoreCatalogItemRequest extends CatalogRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'catalog_category_id' => [
                'required',
                'uuid',
                Rule::exists('catalog_categories', 'id')
                    ->where('workspace_id', $this->workspaceId())
                    ->whereNull('archived_at'),
            ],
            'code' => [
                ...$this->codeRules(),
                Rule::unique('catalog_items', 'code')->where('workspace_id', $this->workspaceId()),
            ],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'unit' => ['required', Rule::enum(CatalogUnit::class)],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
        ];
    }
}
