<?php

declare(strict_types=1);

namespace App\Http\Requests\Seat;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Optional branch / zone filters (and the month for the occupancy view). */
class SeatFilterRequest extends FormRequest
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
        $workspace = $this->route('workspace') ?? $this->user()?->workspace;

        return [
            'branch_id' => ['nullable', 'uuid', Rule::exists('branches', 'id')->where('workspace_id', $workspace?->id)],
            'zone' => ['nullable', 'string', 'max:100'],
            'month' => ['nullable', 'date_format:Y-m'],
        ];
    }
}
