<?php

declare(strict_types=1);

namespace App\Http\Requests\Seat;

use App\Enums\SeatKind;
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
            'kind' => ['sometimes', 'required', Rule::in(SeatKind::values())],
            'zone' => ['sometimes', 'nullable', 'string', 'max:100'],
            'capacity' => ['sometimes', 'required', 'integer', 'min:1', 'max:1000'],
            'is_private' => ['sometimes', 'required', 'boolean'],
            'branch_id' => ['sometimes', 'required', Rule::exists('branches', 'id')->where('workspace_id', $seat->workspace_id)],
            'parent_id' => [
                'sometimes', 'nullable',
                Rule::exists('seats', 'id')->where('workspace_id', $seat->workspace_id),
                Rule::notIn([$seat->id]),
            ],
        ];
    }
}
