<?php

declare(strict_types=1);

namespace App\Http\Requests\Owner;

use App\Enums\PosPermission;
use App\Enums\StaffGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffPermissionsRequest extends FormRequest
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
            'staff_group' => ['nullable', Rule::in(StaffGroup::values())],
            'permissions' => ['present', 'array'],
            'permissions.*' => [Rule::in(PosPermission::values())],
        ];
    }
}
