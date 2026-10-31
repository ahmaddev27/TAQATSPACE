<?php

declare(strict_types=1);

namespace App\Services\Approvals\Handlers;

use App\Contracts\ApprovalHandler;
use App\Enums\ApprovalType;
use App\Models\ApprovalRequest;
use Illuminate\Support\Facades\Validator;

/**
 * Changing a workspace's total seat capacity (`workspaces.total_seats`).
 */
class CapacityApprovalHandler implements ApprovalHandler
{
    public function type(): ApprovalType
    {
        return ApprovalType::Capacity;
    }

    public function validate(array $payload): array
    {
        $data = Validator::make($payload, [
            'total_seats' => ['required', 'integer', 'min:0', 'max:100000'],
        ])->validate();

        return ['total_seats' => (int) $data['total_seats']];
    }

    public function apply(ApprovalRequest $request): void
    {
        $request->workspace()->update(['total_seats' => $request->payload['total_seats']]);
    }

    public function describe(ApprovalRequest $request): array
    {
        return [
            'current' => $request->workspace?->total_seats,
            'proposed' => $request->payload['total_seats'] ?? null,
        ];
    }
}
