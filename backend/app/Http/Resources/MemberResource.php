<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes a workspace member from the owner's perspective.
 *
 * Backed by a Subscription with its `member` (User) and `seat` relations
 * eager-loaded, so the owner sees both the person and their membership terms
 * in a single, flat payload.
 *
 * @mixin Subscription
 */
class MemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $member = $this->member;

        return [
            'subscription_id' => $this->id,
            'user' => [
                'id' => $member?->id,
                'name' => $member?->name,
                'email' => $member?->email,
                'phone' => $member?->phone,
                'specialty' => $member?->specialty,
                'avatar' => $member?->avatar,
            ],
            'status' => $this->status->value,
            'plan_type' => $this->plan_type->value,
            'seat_number' => $this->seat?->seat_number,
            'monthly_price' => $this->monthly_price,
            'join_date' => $this->start_date?->toDateString(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
        ];
    }
}
