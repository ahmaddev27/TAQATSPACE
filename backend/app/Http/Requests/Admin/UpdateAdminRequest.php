<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\AdminPermission;
use App\Enums\AdminRole;
use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

/**
 * Validates updating an existing admin account: status, role, permissions, and
 * an optional password reset. Every field is optional so callers can patch a
 * single attribute. Self-lockout protection is enforced in the service.
 */
class UpdateAdminRequest extends FormRequest
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
            'status' => ['sometimes', 'string', new Enum(UserStatus::class)],
            'admin_role' => ['sometimes', 'string', Rule::in(AdminRole::assignable())],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::in(AdminPermission::values())],
            'password' => ['sometimes', 'nullable', 'string', 'confirmed', Password::defaults()],
        ];
    }

    public function newStatus(): ?UserStatus
    {
        $status = $this->validated('status');

        return $status !== null ? UserStatus::from((string) $status) : null;
    }

    public function adminRole(): ?AdminRole
    {
        $role = $this->validated('admin_role');

        return $role !== null ? AdminRole::from((string) $role) : null;
    }

    /**
     * The requested permission set, or null when the caller did not send the
     * field at all (leave the current grant untouched).
     *
     * @return array<int, string>|null
     */
    public function permissions(): ?array
    {
        if (! $this->has('permissions')) {
            return null;
        }

        /** @var array<int, string> $permissions */
        $permissions = $this->validated('permissions', []);

        return array_values(array_unique($permissions));
    }

    public function newPassword(): ?string
    {
        $password = $this->validated('password');

        return is_string($password) && $password !== '' ? $password : null;
    }
}
