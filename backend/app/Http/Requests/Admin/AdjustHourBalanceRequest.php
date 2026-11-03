<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Manual balance correction. The reason is mandatory so every adjustment is explainable.
 */
class AdjustHourBalanceRequest extends FormRequest
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
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'minutes' => ['required', 'integer', 'not_in:0', 'between:-600000,600000'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
