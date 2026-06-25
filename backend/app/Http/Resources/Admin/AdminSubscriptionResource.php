<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Subscription;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Read-only subscription row for the super-admin tracking list. Flattens the
 * member and workspace names the admin needs without the owner-side invoice
 * bundle carried by the default SubscriptionResource.
 *
 * @mixin Subscription
 */
class AdminSubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'seat_id' => $this->seat_id,
            'plan_type' => $this->plan_type->value,
            'monthly_price' => $this->monthly_price,
            'status' => $this->status->value,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),

            'member' => $this->whenLoaded('member', fn (): ?array => $this->member === null ? null : [
                'id' => $this->member->id,
                'name' => $this->member->name,
                'email' => $this->member->email,
                'avatar' => MediaUrl::resolve($this->member->avatar),
            ]),
            'workspace' => $this->whenLoaded('workspace', fn (): ?array => $this->workspace === null ? null : [
                'id' => $this->workspace->id,
                'name' => $this->workspace->name,
                'city' => $this->workspace->city,
            ]),
        ];
    }
}
