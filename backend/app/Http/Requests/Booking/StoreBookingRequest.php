<?php

declare(strict_types=1);

namespace App\Http\Requests\Booking;

use App\Enums\SeatType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
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
            'workspace_id' => ['required', 'string', 'uuid', 'exists:workspaces,id'],
            'preferred_seat_type' => ['nullable', Rule::in(SeatType::values())],
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
