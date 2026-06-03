<?php

declare(strict_types=1);

namespace App\Http\Requests\Seat;

use App\Enums\SeatStatus;
use App\Enums\SeatType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSeatRequest extends FormRequest
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
            'seat_number' => ['required', 'string', 'max:50'],
            'type' => ['required', Rule::in(SeatType::values())],
            'status' => ['nullable', Rule::in(SeatStatus::values())],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
