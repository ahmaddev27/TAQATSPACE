<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Validates an admin user-status transition. Authorization is enforced by the
 * route middleware (auth:sanctum + role.admin).
 */
class UpdateUserStatusRequest extends FormRequest
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
            'status' => ['required', 'string', new Enum(UserStatus::class)],
        ];
    }

    public function status(): UserStatus
    {
        return UserStatus::from((string) $this->validated('status'));
    }
}
