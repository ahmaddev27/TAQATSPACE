<?php

declare(strict_types=1);

namespace App\Http\Requests\Owner;

use App\Enums\SubscriptionStatus;
use App\Http\Requests\Messaging\BroadcastRequest;
use Illuminate\Validation\Rule;

/**
 * Owner (workspace) broadcast: the audience is the owner's OWN members,
 * segmentable by subscription status. Behind auth:sanctum + role.owner; the
 * controller re-scopes every recipient to the owner's workspace.
 */
class OwnerBroadcastRequest extends BroadcastRequest
{
    /**
     * @return array<string, mixed>
     */
    protected function segmentRules(): array
    {
        return [
            'segment' => ['array'],
            'segment.status' => ['nullable', Rule::in(SubscriptionStatus::values())],
        ];
    }
}
