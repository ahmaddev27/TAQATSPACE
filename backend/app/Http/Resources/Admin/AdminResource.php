<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\User;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An admin account as shown in the admin-management list. Carries the profile
 * header plus the Spatie roles and effective permissions so the UI can render
 * badges and pre-check the edit form.
 *
 * Roles/permissions are eager-loaded by the service (no N+1).
 *
 * @mixin User
 */
class AdminResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar' => MediaUrl::resolve($this->avatar),
            'status' => $this->status->value,
            'roles' => $this->getRoleNames()->values()->all(),
            'is_super_admin' => $this->isSuperAdmin(),
            'permissions' => $this->effectivePermissionNames(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
