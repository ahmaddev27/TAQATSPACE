<?php

declare(strict_types=1);

namespace App\Http\Requests\Workspace;

use App\Enums\SeatType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSeatTypesRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ownership is enforced via the 'manage-workspace' gate in the controller.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'seat_types' => ['required', 'array'],
            'seat_types.*.type' => ['required', Rule::in(SeatType::values())],
            'seat_types.*.price_monthly' => ['nullable', 'numeric', 'min:0'],
            'seat_types.*.price_daily' => ['nullable', 'numeric', 'min:0'],
            'seat_types.*.capacity' => ['nullable', 'integer', 'min:0'],
            'seat_types.*.enabled' => ['boolean'],
        ];
    }
}
