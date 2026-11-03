<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

class UpdateCatalogCategoryRequest extends CatalogRequest
{
    /**
     * `code` and `kind` are fixed at creation: other documents key off them.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['prohibited'],
            'kind' => ['prohibited'],
            'name_ar' => ['sometimes', 'string', 'max:255'],
            'name_en' => ['sometimes', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
        ];
    }
}
