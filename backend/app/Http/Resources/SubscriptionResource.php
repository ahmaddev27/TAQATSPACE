<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Subscription
 */
class SubscriptionResource extends JsonResource
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
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'monthly_price' => $this->monthly_price,
            'status' => $this->status->value,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),

            'workspace' => $this->whenLoaded('workspace', fn () => [
                'id' => $this->workspace->id,
                'name' => $this->workspace->name,
                'city' => $this->workspace->city,
                'address' => $this->workspace->address,
                'photos' => $this->workspace->photos,
                // Dedicated workspace logo, falling back to the owner avatar.
                'logo' => $this->workspace->logoUrl(),
            ]),
            'seat' => $this->whenLoaded('seat', fn () => $this->seat === null ? null : [
                'id' => $this->seat->id,
                'seat_number' => $this->seat->seat_number,
                'type' => $this->seat->type->value,
                'status' => $this->seat->status->value,
            ]),
            'invoices' => InvoiceLineResource::collection($this->whenLoaded('invoices')),
        ];
    }
}
