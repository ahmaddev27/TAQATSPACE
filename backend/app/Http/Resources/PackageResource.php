<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\InternetPackage;
use App\Models\User;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InternetPackage
 */
class PackageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'name' => $this->name,
            'speed_mbps' => $this->speed_mbps,
            'price' => $this->price,
            'data_limit_gb' => $this->data_limit_gb,
            'is_unlimited' => $this->is_unlimited,
            'is_active' => $this->is_active,
            'assigned_members_count' => $this->whenCounted('members'),
            'members' => $this->whenLoaded('members', fn () => $this->members->map(static fn (User $member): array => [
                'id' => $member->id,
                'name' => $member->name,
                'avatar' => MediaUrl::resolve($member->avatar),
                'assigned_at' => $member->pivot?->assigned_at,
            ])->all()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
