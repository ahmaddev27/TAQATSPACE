<?php

declare(strict_types=1);

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Filters of the gap list. Ownership is enforced by the controller; the router must be the workspace's own. */
class ListRouterGapsRequest extends FormRequest
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
            'router_id' => ['nullable', 'uuid', Rule::exists('routers', 'id')->where('workspace_id', $this->user()?->workspace?->getKey())],
            'open' => ['nullable', 'boolean'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
