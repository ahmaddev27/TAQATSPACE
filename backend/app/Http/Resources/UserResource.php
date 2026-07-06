<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\PosPermission;
use App\Models\User;
use App\Services\Pos\CashierManagementService;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
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
            'phone' => $this->phone,
            'gender' => $this->gender?->value,
            'role' => $this->role->value,
            'status' => $this->status->value,
            'needs_onboarding' => $this->needsOnboarding(),
            'pending_cashier_invitation' => $this->pendingCashierInvitation(),
            'specialty' => $this->specialty,
            'bio' => $this->bio,
            'avatar' => $this->avatarUrl(),
            // Only the local email/password account (the internal admin) may change
            // its password; SSO-provisioned users have their credentials at the IdP.
            'can_change_password' => $this->sso_sub === null,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            // Staff-only authorization context so the SPA can adapt the admin UI
            // (e.g. hide the admin-management nav for a non-super-admin). Resolved
            // only for admin accounts to keep the common path query-free.
            ...$this->adminContext(),
            // Cashier POS grants so the terminal can hide controls the cashier
            // lacks (e.g. the Refund button without `pos_refund`). Resolved only
            // for cashier accounts to keep the common path query-free.
            ...$this->cashierContext(),
        ];
    }

    /**
     * Spatie roles + effective permissions, emitted only for admin accounts.
     * Non-admin users get an empty array (no extra queries).
     *
     * @return array<string, mixed>
     */
    private function adminContext(): array
    {
        if (! $this->isAdmin()) {
            return [];
        }

        return [
            'is_super_admin' => $this->isSuperAdmin(),
            'permissions' => $this->effectivePermissionNames(),
        ];
    }

    /**
     * A cashier's effective POS permission names, emitted under the same
     * `permissions` key the admin branch uses so the SPA reads one field.
     * Non-cashier users get an empty array (no extra queries).
     *
     * @return array<string, mixed>
     */
    private function cashierContext(): array
    {
        if (! $this->isCashier()) {
            return [];
        }

        return [
            'permissions' => $this->getPermissionNames()->values()->all(),
        ];
    }

    /**
     * A still-open cashier invitation addressed to this user, surfaced only while
     * they still need onboarding so the SPA can offer accept/decline. Resolved
     * lazily to keep the common (already-onboarded) path query-free.
     *
     * @return array{id: string, workspace_name: ?string, permissions: array<int, string>}|null
     */
    private function pendingCashierInvitation(): ?array
    {
        if (! $this->needsOnboarding()) {
            return null;
        }

        $invitation = app(CashierManagementService::class)->pendingForEmail((string) $this->email);

        if ($invitation === null) {
            return null;
        }

        return [
            'id' => $invitation->id,
            'workspace_name' => $invitation->workspace?->name,
            'permissions' => $invitation->permissions ?? PosPermission::defaultsForCashier(),
        ];
    }

    /** Resolve the stored avatar path to a viewable URL (local or presigned S3). */
    private function avatarUrl(): ?string
    {
        return MediaUrl::resolve($this->avatar);
    }
}
