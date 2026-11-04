<?php

declare(strict_types=1);

namespace App\Http\Requests\Seat;

use App\Enums\SeatKind;
use App\Enums\SeatType;
use App\Services\Seat\BulkSeatCreator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkStoreSeatRequest extends FormRequest
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
            'start' => ['required', 'integer', 'min:0', 'max:999999'],
            'count' => ['required', 'integer', 'min:1', 'max:'.BulkSeatCreator::MAX_COUNT],
            'prefix' => ['nullable', 'string', 'max:20'],
            'pad' => ['nullable', 'integer', 'min:0', 'max:6'],
            'type' => ['required', Rule::in(SeatType::values())],
            'kind' => ['nullable', Rule::in(SeatKind::values())],
            'zone' => ['nullable', 'string', 'max:100'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'is_private' => ['nullable', 'boolean'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('workspace_id', $workspaceId)],
        ];
    }
}
