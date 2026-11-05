<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CreditNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CreditNote
 */
class CreditNoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'invoice_id' => $this->invoice_id,
            'invoice_number' => $this->invoice?->invoice_number,
            'customer' => $this->invoice?->customer === null ? null : [
                'id' => $this->invoice->customer->id,
                'name' => $this->invoice->customer->name,
            ],
            'amount' => (string) $this->amount,
            'refund_amount' => (string) $this->refund_amount,
            'reason' => $this->reason,
            'issued_by' => $this->issuer === null ? null : ['id' => $this->issuer->id, 'name' => $this->issuer->name],
            'issued_at' => $this->issued_at?->toIso8601String(),
            'voided_at' => $this->voided_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
