<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\AdminPermission;
use App\Enums\AdminRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validates creating a new admin account. Authorization is enforced by the
 * route middleware (auth:sanctum + can.manage_admins).
 */
class StoreAdminRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'admin_role' => ['required', 'string', Rule::in(AdminRole::assignable())],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::in(AdminPermission::values())],
        ];
    }

    public function adminRole(): AdminRole
    {
        return AdminRole::from((string) $this->validated('admin_role'));
    }

    /**
     * The requested permission set. Null signals "use the role's defaults"; an
     * (empty) array is an explicit, honoured selection.
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
}
