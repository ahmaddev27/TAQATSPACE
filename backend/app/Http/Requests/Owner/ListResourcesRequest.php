<?php

declare(strict_types=1);

namespace App\Http\Requests\Owner;

use App\Enums\ResourceStatus;
use App\Enums\ResourceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the owner resources listing filters: type + availability status.
 */
class ListResourcesRequest extends FormRequest
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
            'type' => ['nullable', 'string', Rule::in([...ResourceType::values(), 'all'])],
            'status' => ['nullable', 'string', Rule::in([...ResourceStatus::values(), 'all'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
    }
}
