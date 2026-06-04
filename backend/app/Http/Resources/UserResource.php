<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
            'specialty' => $this->specialty,
            'bio' => $this->bio,
            'avatar' => $this->avatarUrl(),
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /** Resolve the stored avatar path to a public URL (local or S3). */
    private function avatarUrl(): ?string
    {
        if (! $this->avatar) {
            return null;
        }

        $disk = Storage::disk((string) config('filesystems.media', 'public'));

        return $disk->exists($this->avatar)
            ? $disk->url($this->avatar)
            : $this->avatar;
    }
}
