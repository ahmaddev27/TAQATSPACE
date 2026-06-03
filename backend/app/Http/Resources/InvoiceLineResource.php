<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight invoice projection used inline inside a subscription detail.
 *
 * The full invoice module owns its own resource; this only exposes the fields
 * a member needs when viewing a subscription, so the modules stay decoupled.
 *
 * @mixin Invoice
 */
class InvoiceLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'amount' => $this->amount,
            'status' => $this->status->value,
            'due_date' => $this->due_date?->toDateString(),
            'paid_at' => $this->paid_at?->toIso8601String(),
        ];
    }
}
