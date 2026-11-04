<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\UnbilledCharge;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UnbilledCharge
 */
class UnbilledChargeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'customer' => $this->whenLoaded('customer', fn (): array => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
            ]),
            'subscription_id' => $this->subscription_id,
            'catalog_item_id' => $this->catalog_item_id,
            'description' => $this->description,
            'qty' => (string) $this->qty,
            'unit_price' => (string) $this->unit_price,
            'amount' => (string) $this->amount,
            'source' => $this->source->value,
            'source_ref' => $this->source_ref,
            'status' => $this->status->value,
            'invoice_line_id' => $this->invoice_line_id,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
