<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Enums\CatalogUnit;
use Illuminate\Validation\Rule;

class UpdateCatalogItemRequest extends CatalogRequest
{
    /**
     * `code` is fixed at creation: other documents key off it.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['prohibited'],
            'catalog_category_id' => [
                'sometimes',
                'uuid',
                Rule::exists('catalog_categories', 'id')
                    ->where('workspace_id', $this->workspaceId())
                    ->whereNull('archived_at'),
            ],
            'name_ar' => ['sometimes', 'string', 'max:255'],
            'name_en' => ['sometimes', 'string', 'max:255'],
            'unit' => ['sometimes', Rule::enum(CatalogUnit::class)],
            'price' => ['sometimes', 'numeric', 'min:0', 'max:9999999999.99'],
        ];
    }
}
