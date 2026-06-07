<?php

declare(strict_types=1);

namespace App\Http\Requests\Owner;

use App\Enums\ResourceStatus;
use App\Enums\ResourceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates an edit to an existing workspace resource. Every field is optional
 * (`sometimes`) so the owner may patch a subset.
 */
class UpdateResourceRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', Rule::in(ResourceType::values())],
            'quantity' => ['sometimes', 'required', 'integer', 'min:0', 'max:100000'],
            'status' => ['sometimes', 'required', Rule::in(ResourceStatus::values())],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
