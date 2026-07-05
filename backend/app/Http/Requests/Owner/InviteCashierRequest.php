<?php

declare(strict_types=1);

namespace App\Http\Requests\Owner;

use App\Enums\PosPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteCashierRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [Rule::in(PosPermission::values())],
        ];
    }
}
