<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Enums\CatalogKind;
use App\Services\Finance\CatalogAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && app(CatalogAccess::class)->canView($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'kind' => ['nullable', Rule::enum(CatalogKind::class)],
            'catalog_category_id' => ['nullable', 'uuid'],
            'archived' => ['nullable', Rule::in(['exclude', 'include', 'only'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
    }
}
