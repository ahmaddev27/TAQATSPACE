<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Seat;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Seat
 */
class SeatResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'seat_number' => $this->seat_number,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'notes' => $this->notes,
            'assigned_member' => $this->whenLoaded('assignedMember', fn () => $this->assignedMember === null ? null : [
                'id' => $this->assignedMember->id,
                'name' => $this->assignedMember->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
