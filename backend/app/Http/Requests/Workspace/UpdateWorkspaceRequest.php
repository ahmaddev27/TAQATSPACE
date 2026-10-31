<?php

declare(strict_types=1);

namespace App\Http\Requests\Workspace;

use App\Enums\WorkspaceCurrency;
use App\Enums\WorkspaceTimezone;
use App\Rules\Iban;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'city_id' => ['sometimes', 'required', 'string', 'exists:cities,id'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'contact_email' => ['sometimes', 'nullable', 'email:rfc', 'max:191'],
            'bank_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'iban' => ['sometimes', 'nullable', new Iban],
            'timezone' => ['sometimes', 'required', Rule::enum(WorkspaceTimezone::class)],
            'currency' => ['sometimes', 'required', Rule::enum(WorkspaceCurrency::class)],
            'wifi_ssid' => ['sometimes', 'nullable', 'string', 'max:64'],
            'wifi_password' => ['sometimes', 'nullable', 'string', 'max:128'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'amenities' => ['sometimes', 'nullable', 'array'],
            'amenities.*' => ['string', 'max:60'],
            'working_hours' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
