<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PaymentVoucher;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PaymentVoucher
 */
class PaymentVoucherResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'customer_id' => $this->customer_id,
            'customer' => $this->whenLoaded('customer', fn (): ?array => $this->customer === null ? null : [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
            ]),
            'method' => $this->method->value,
            'amount' => (string) $this->amount,
            'reason' => $this->reason,
            'reference' => $this->reference,
            'wallet_entry_id' => $this->wallet_entry_id,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'voided_at' => $this->voided_at?->toIso8601String(),
            'void_reason' => $this->void_reason,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
