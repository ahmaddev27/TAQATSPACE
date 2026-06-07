<?php

declare(strict_types=1);

namespace App\Http\Requests\Owner;

use App\Enums\ResourceStatus;
use App\Enums\ResourceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a new workspace resource submitted by the owner. The resource is
 * attached to the owner's own workspace server-side; no workspace_id is taken.
 */
class StoreResourceRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(ResourceType::values())],
            'quantity' => ['required', 'integer', 'min:0', 'max:100000'],
            'status' => ['required', Rule::in(ResourceStatus::values())],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
