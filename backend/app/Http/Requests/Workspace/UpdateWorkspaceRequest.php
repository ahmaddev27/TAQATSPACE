<?php

declare(strict_types=1);

namespace App\Http\Requests\Workspace;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ownership is enforced via the 'manage-workspace' gate in the controller.
        return true;
    }

    /**
     * All fields optional (partial update). status/owner_id are intentionally
     * absent and are never accepted on this endpoint. total_seats and
     * price_per_month are derived from the seat types (the single source of
     * truth) and are likewise not accepted here.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'address' => ['sometimes', 'required', 'string', 'max:255'],
            'city' => ['sometimes', 'required', 'string', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'amenities' => ['sometimes', 'nullable', 'array'],
            'amenities.*' => ['string', 'max:60'],
            'working_hours' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
