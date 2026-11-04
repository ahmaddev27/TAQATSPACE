<?php

declare(strict_types=1);

namespace App\Http\Requests\Seat;

use App\Enums\SeatKind;
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
        $workspaceId = $this->user()?->workspace?->id;

        return [
            'seat_number' => ['required', 'string', 'max:50'],
            'type' => ['required', Rule::in(SeatType::values())],
            'status' => ['nullable', Rule::in(SeatStatus::values())],
            'notes' => ['nullable', 'string', 'max:500'],
            'kind' => ['nullable', Rule::in(SeatKind::values())],
            'zone' => ['nullable', 'string', 'max:100'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'is_private' => ['nullable', 'boolean'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('workspace_id', $workspaceId)],
            'parent_id' => ['nullable', Rule::exists('seats', 'id')->where('workspace_id', $workspaceId)],
        ];
    }
}
