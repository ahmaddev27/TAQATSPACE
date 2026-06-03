<?php

declare(strict_types=1);

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListMembersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(['active', 'suspended', 'all'])],
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', Rule::in(['join_date', 'name', 'status'])],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
    }
}
