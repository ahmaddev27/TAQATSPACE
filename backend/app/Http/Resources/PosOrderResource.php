<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PosOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PosOrder
 */
class PosOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'source' => $this->source->value,
            'status' => $this->status->value,
            'customer_name' => $this->customer_name,
            'note' => $this->note,
            'member' => $this->whenLoaded('member', fn () => $this->member ? [
                'id' => $this->member->id,
                'name' => $this->member->name,
            ] : null),
            'subtotal' => (string) $this->subtotal,
            'discount' => (string) $this->discount,
            'total' => (string) $this->total,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'refunded_at' => $this->refunded_at?->toIso8601String(),
            'items' => PosOrderItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
