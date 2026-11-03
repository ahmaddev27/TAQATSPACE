<?php

declare(strict_types=1);

namespace App\Http\Requests\Workspace;

use App\Enums\SeatingMode;
use App\Enums\SeatType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitOperationsSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ownership is enforced via the 'manage-workspace' gate in the controller.
        return true;
    }

    /**
     * Every section is optional, but at least one must be sent.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'seat_types' => ['required_without_all:seating_mode,working_hours,tax_rate', 'array', 'max:'.count(SeatType::cases())],
            'seat_types.*.type' => ['required', Rule::in(SeatType::values()), 'distinct'],
            'seat_types.*.price_monthly' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'seat_types.*.price_daily' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'seat_types.*.capacity' => ['integer', 'min:0', 'max:100000'],
            'seat_types.*.enabled' => ['boolean'],
            'seating_mode' => ['nullable', Rule::enum(SeatingMode::class)],
            'working_hours' => ['nullable', 'array', 'min:1'],
            'tax_rate' => ['nullable', 'numeric', 'between:0,100'],
        ];
    }
}
