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
        return [
            'type' => ['sometimes', 'required', Rule::in(SeatType::values())],
            'status' => ['sometimes', 'required', Rule::in(SeatStatus::values())],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
