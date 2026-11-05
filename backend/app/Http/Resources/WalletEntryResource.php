<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\WalletEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WalletEntry
 */
class WalletEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'type' => $this->type->value,
            // Signed decimal string: negative takes from the balance.
            'amount' => (string) $this->amount,
            'method' => $this->method,
            'reason' => $this->reason,
            'related_type' => $this->related_type,
            'related_id' => $this->related_id,
            'created_by' => $this->whenLoaded('creator', fn (): ?array => $this->creator === null ? null : [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
