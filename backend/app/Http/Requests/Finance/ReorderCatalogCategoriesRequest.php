<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

class ReorderCatalogCategoriesRequest extends CatalogRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
