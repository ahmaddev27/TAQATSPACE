<?php

declare(strict_types=1);

namespace App\Services\Approvals\Handlers;

use App\Enums\ApprovalType;
use App\Enums\SeatingMode;
use App\Models\ApprovalRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Changing how many seats each type offers and how seats are assigned. The total
 * (`workspaces.total_seats`) is derived from the seat types, never set directly.
 */
class CapacityApprovalHandler extends SeatTypeApprovalHandler
{
    public function type(): ApprovalType
    {
        return ApprovalType::Capacity;
    }

    protected function columns(): array
    {
        return ['capacity', 'enabled'];
    }

    protected function columnRules(): array
    {
        return [
            'capacity' => ['required', 'integer', 'min:0', 'max:100000'],
            'enabled' => ['required', 'boolean'],
        ];
    }

    public function validate(array $payload): array
    {
        $rules = $this->seatTypeRules('seating_mode');
        $rules['seating_mode'] = ['sometimes', Rule::enum(SeatingMode::class)];

        return Validator::make($payload, $rules)->validate();
    }

    public function apply(ApprovalRequest $request): void
    {
        $this->applySeatTypes($request);

        if (isset($request->payload['seating_mode'])) {
            $request->workspace->update(['seating_mode' => $request->payload['seating_mode']]);
        }
    }

    public function describe(ApprovalRequest $request): array
    {
        $current = ['seat_types' => $this->currentSeatTypes($request)];
        $proposed = ['seat_types' => $request->payload['seat_types'] ?? []];

        if (isset($request->payload['seating_mode'])) {
            $current['seating_mode'] = $request->workspace?->seating_mode?->value;
            $proposed['seating_mode'] = $request->payload['seating_mode'];
        }

        return ['current' => $current, 'proposed' => $proposed];
    }
}
