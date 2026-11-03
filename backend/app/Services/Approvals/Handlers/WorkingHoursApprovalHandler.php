<?php

declare(strict_types=1);

namespace App\Services\Approvals\Handlers;

use App\Contracts\ApprovalHandler;
use App\Enums\ApprovalType;
use App\Models\ApprovalRequest;
use Illuminate\Support\Facades\Validator;

/**
 * Changing a workspace's weekly opening hours (`workspaces.working_hours`).
 * The per-day shape is owned by the settings UI, so only the envelope is checked.
 */
class WorkingHoursApprovalHandler implements ApprovalHandler
{
    public function type(): ApprovalType
    {
        return ApprovalType::WorkingHours;
    }

    public function validate(array $payload): array
    {
        $data = Validator::make($payload, [
            'working_hours' => ['required', 'array', 'min:1'],
        ])->validate();

        return ['working_hours' => $data['working_hours']];
    }

    public function apply(ApprovalRequest $request): void
    {
        $request->workspace()->update(['working_hours' => $request->payload['working_hours']]);
    }

    public function describe(ApprovalRequest $request): array
    {
        return [
            'current' => $request->workspace?->working_hours,
            'proposed' => $request->payload['working_hours'] ?? null,
        ];
    }
}
