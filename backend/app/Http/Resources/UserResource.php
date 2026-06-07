<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
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
            'role' => $this->role->value,
            'status' => $this->status->value,
            'needs_onboarding' => $this->needsOnboarding(),
            'specialty' => $this->specialty,
            'bio' => $this->bio,
            'avatar' => $this->avatarUrl(),
            // Only the local email/password account (the internal admin) may change
            // its password; SSO-provisioned users have their credentials at the IdP.
            'can_change_password' => $this->sso_sub === null,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /** Resolve the stored avatar path to a viewable URL (local or presigned S3). */
    private function avatarUrl(): ?string
    {
        return MediaUrl::resolve($this->avatar);
    }
}
