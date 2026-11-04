<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Receipt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/**
 * @mixin Receipt
 */
class ReceiptResource extends JsonResource
{
    private const FILE_URL_MINUTES = 10;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $allocated = $this->allocatedTotal();

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
            'allocated_amount' => $allocated,
            'unallocated_amount' => bcsub((string) $this->amount, $allocated, 2),
            'received_at' => $this->received_at?->toIso8601String(),
            'collected_at' => $this->collected_at?->toIso8601String(),
            'reference' => $this->reference,
            'cheque_status' => $this->cheque_status?->value,
            'is_collected' => $this->isCollected(),
            'receipt_url' => $this->receipt_path === null ? null : URL::temporarySignedRoute(
                'finance.receipts.file',
                now()->addMinutes(self::FILE_URL_MINUTES),
                ['receipt' => $this->id],
            ),
            'voided_at' => $this->voided_at?->toIso8601String(),
            'void_reason' => $this->void_reason,
            'allocations' => $this->whenLoaded('allocations', fn () => $this->allocations->map(static fn ($allocation): array => [
                'invoice_id' => $allocation->invoice_id,
                'invoice_number' => $allocation->invoice?->invoice_number,
                'amount' => (string) $allocation->amount,
            ])->all()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function allocatedTotal(): string
    {
        $total = '0.00';

        foreach ($this->allocations as $allocation) {
            $total = bcadd($total, (string) $allocation->amount, 2);
        }

        return $total;
    }
}
