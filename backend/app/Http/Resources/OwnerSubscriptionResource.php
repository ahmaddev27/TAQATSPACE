<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Subscription;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes a subscription from the workspace owner's perspective: the member,
 * plan terms, seat, and the period so the owner can track status and renewals.
 *
 * Backed by a Subscription with `member` and `seat` eager-loaded.
 *
 * @mixin Subscription
 */
class OwnerSubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $member = $this->member;

        return [
            'id' => $this->id,
            'member' => [
                'id' => $member?->id,
                'name' => $member?->name,
                'email' => $member?->email,
                'phone' => $member?->phone,
                'specialty' => $member?->specialty,
                'avatar' => MediaUrl::resolve($member?->avatar),
            ],
            'plan_type' => $this->plan_type->value,
            'monthly_price' => $this->monthly_price,
            'seat_number' => $this->seat?->seat_number,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'status' => $this->status->value,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
