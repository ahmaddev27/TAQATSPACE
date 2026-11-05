<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Invoice;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    private const CURRENCY = '₪';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subscription_id' => $this->subscription_id,
            'invoice_number' => $this->invoice_number,
            'amount' => $this->amount,
            'amount_paid' => $this->amount_paid,
            'credited_amount' => (string) $this->credited_amount,
            'remaining_amount' => number_format(
                max(0, (float) $this->amount - (float) $this->credited_amount - (float) $this->amount_paid),
                2,
                '.',
                '',
            ),
            'amount_formatted' => $this->formattedAmount(),
            'currency' => self::CURRENCY,
            'status' => $this->status->value,
            'doc_status' => $this->doc_status->value,
            'payment_status' => $this->payment_status->value,
            'customer_id' => $this->customer_id,
            'subtotal' => $this->subtotal,
            'tax_rate' => $this->tax_rate,
            'tax_amount' => $this->tax_amount,
            'total' => $this->total,
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
            'issued_at' => $this->issued_at?->toIso8601String(),
            'voided_at' => $this->voided_at?->toIso8601String(),
            'void_reason' => $this->void_reason,
            'customer' => $this->whenLoaded('customer', fn (): ?array => $this->customer === null ? null : [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
            ]),
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(static fn ($line): array => [
                'id' => $line->id,
                'description' => $line->description,
                'qty' => $line->qty,
                'unit_price' => $line->unit_price,
                'amount' => $line->amount,
                'unbilled_charge_id' => $line->unbilled_charge_id,
                'catalog_item_id' => $line->catalog_item_id,
            ])->all()),
            'due_date' => $this->due_date?->toDateString(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'is_overdue' => $this->isOverdue(),
            'invoice_pdf_path' => $this->invoice_pdf_path,
            'pdf_url' => MediaUrl::resolve($this->invoice_pdf_path),
            'receipt_path' => $this->receipt_path,
            'receipt_url' => MediaUrl::resolve($this->receipt_path),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(static fn ($p): array => [
                'id' => $p->id,
                'amount' => $p->amount,
                'paid_at' => $p->paid_at?->toIso8601String(),
                // Once a receipt mirrors the payment its proof is private: a signed link, never a public one.
                'receipt_url' => $p->relationLoaded('receipt') && $p->receipt !== null
                    ? $p->receipt->fileUrl()
                    : MediaUrl::resolve($p->receipt_path),
            ])->all()),
            'receipt_rejected_reason' => $this->receipt_rejected_reason,
            'receipt_reviewed_at' => $this->receipt_reviewed_at?->toIso8601String(),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),

            // Flat member/workspace for the admin billing list (contract shape).
            // Present only when the subscription chain is eager-loaded.
            'member' => $this->flatMember(),
            'workspace' => $this->flatWorkspace(),

            // Present only when the controller eager-loads the subscription chain.
            'subscription' => $this->whenLoaded('subscription', fn (): ?array => $this->subscription === null ? null : [
                'id' => $this->subscription->id,
                'plan_type' => $this->subscription->plan_type->value,
                'monthly_price' => $this->subscription->monthly_price,
                'start_date' => $this->subscription->start_date?->toDateString(),
                'end_date' => $this->subscription->end_date?->toDateString(),
                'status' => $this->subscription->status->value,
                'workspace' => $this->subscription->relationLoaded('workspace') && $this->subscription->workspace !== null
                    ? [
                        'id' => $this->subscription->workspace->id,
                        'name' => $this->subscription->workspace->name,
                        'city' => $this->subscription->workspace->city,
                    ]
                    : null,
                'member' => $this->subscription->relationLoaded('member') && $this->subscription->member !== null
                    ? [
                        'id' => $this->subscription->member->id,
                        'name' => $this->subscription->member->name,
                        'email' => $this->subscription->member->email,
                        'phone' => $this->subscription->member->phone,
                    ]
                    : null,
                'seat' => $this->subscription->relationLoaded('seat') && $this->subscription->seat !== null
                    ? [
                        'id' => $this->subscription->seat->id,
                        'seat_number' => $this->subscription->seat->seat_number,
                    ]
                    : null,
            ]),
        ];
    }

    /**
     * Amount with the shekel symbol, e.g. "₪ 150.00".
     */
    private function formattedAmount(): string
    {
        return self::CURRENCY.' '.number_format((float) $this->amount, 2);
    }

    /**
     * Flattened member contract block ({ id, name }) resolved through the
     * eager-loaded subscription. Null until the chain is loaded.
     *
     * @return array{id: string, name: ?string}|null
     */
    private function flatMember(): ?array
    {
        $member = $this->resource->relationLoaded('subscription')
            && $this->subscription?->relationLoaded('member')
            ? $this->subscription->member
            : null;

        return $member === null ? null : ['id' => $member->id, 'name' => $member->name];
    }

    /**
     * Flattened workspace contract block ({ id, name }) resolved through the
     * eager-loaded subscription. Null until the chain is loaded.
     *
     * @return array{id: string, name: ?string}|null
     */
    private function flatWorkspace(): ?array
    {
        $workspace = $this->resource->relationLoaded('subscription')
            && $this->subscription?->relationLoaded('workspace')
            ? $this->subscription->workspace
            : null;

        return $workspace === null ? null : ['id' => $workspace->id, 'name' => $workspace->name];
    }
}
