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
            'amount_formatted' => $this->formattedAmount(),
            'currency' => self::CURRENCY,
            'status' => $this->status->value,
            'due_date' => $this->due_date?->toDateString(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'is_overdue' => $this->isOverdue(),
            'invoice_pdf_path' => $this->invoice_pdf_path,
            'pdf_url' => MediaUrl::resolve($this->invoice_pdf_path),
            'receipt_path' => $this->receipt_path,
            'receipt_url' => MediaUrl::resolve($this->receipt_path),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),

            // Flat member/workspace for the admin billing list (contract shape).
            // Present only when the subscription chain is eager-loaded.
            'member' => $this->flatMember(),
            'workspace' => $this->flatWorkspace(),

            // Present only when the controller eager-loads the subscription chain.
            'subscription' => $this->whenLoaded('subscription', fn (): array => [
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
