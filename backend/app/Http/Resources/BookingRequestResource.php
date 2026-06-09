<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\BookingRequest;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BookingRequest
 */
class BookingRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'preferred_seat_type' => $this->preferred_seat_type?->value,
            'message' => $this->message,
            'status' => $this->status->value,
            'rejection_reason' => $this->rejection_reason,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'member' => $this->whenLoaded('member', fn () => [
                'id' => $this->member->id,
                'name' => $this->member->name,
                'email' => $this->member->email,
                'specialty' => $this->member->specialty,
                'avatar' => MediaUrl::resolve($this->member->avatar),
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
