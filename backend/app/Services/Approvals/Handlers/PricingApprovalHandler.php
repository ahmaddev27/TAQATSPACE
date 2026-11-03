<?php

declare(strict_types=1);

namespace App\Services\Approvals\Handlers;

use App\Enums\ApprovalType;
use App\Models\ApprovalRequest;
use Illuminate\Support\Facades\Validator;

/**
 * Changing the per-seat-type prices and the tax percentage. Applying the prices
 * also refreshes the public "starting from" price derived from them.
 */
class PricingApprovalHandler extends SeatTypeApprovalHandler
{
    public function type(): ApprovalType
    {
        return ApprovalType::Pricing;
    }

    protected function columns(): array
    {
        return ['price_monthly', 'price_daily'];
    }

    protected function columnRules(): array
    {
        return [
            'price_monthly' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'price_daily' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ];
    }

    public function validate(array $payload): array
    {
        $rules = $this->seatTypeRules('tax_rate');
        $rules['tax_rate'] = ['sometimes', 'numeric', 'between:0,100'];

        return Validator::make($payload, $rules)->validate();
    }

    public function apply(ApprovalRequest $request): void
    {
        $this->applySeatTypes($request);

        if (isset($request->payload['tax_rate'])) {
            $request->workspace->update(['tax_rate' => $request->payload['tax_rate']]);
        }
    }

    public function describe(ApprovalRequest $request): array
    {
        $current = ['seat_types' => $this->currentSeatTypes($request)];
        $proposed = ['seat_types' => $request->payload['seat_types'] ?? []];

        if (isset($request->payload['tax_rate'])) {
            $current['tax_rate'] = $request->workspace?->tax_rate;
            $proposed['tax_rate'] = $request->payload['tax_rate'];
        }

        return ['current' => $current, 'proposed' => $proposed];
    }
}
