<?php

declare(strict_types=1);

namespace App\Http\Requests\Seat;

use App\Enums\SeatStatus;
use App\Enums\SeatType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSeatRequest extends FormRequest
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
        $seat = $this->route('seat');

        return [
            'seat_number' => [
                'sometimes', 'required', 'string', 'max:20',
                // Seat labels must stay unique within the workspace.
                Rule::unique('seats', 'seat_number')
                    ->where('workspace_id', $seat->workspace_id)
                    ->ignore($seat->id),
            ],
            'type' => ['sometimes', 'required', Rule::in(SeatType::values())],
            'status' => ['sometimes', 'required', Rule::in(SeatStatus::values())],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
