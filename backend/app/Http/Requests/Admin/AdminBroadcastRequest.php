<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Requests\Messaging\BroadcastRequest;
use Illuminate\Validation\Rule;

/**
 * Admin (platform) broadcast: the audience is the whole user base, segmentable
 * by account role and/or status. Behind auth:sanctum + role.admin.
 */
class AdminBroadcastRequest extends BroadcastRequest
{
    /**
     * @return array<string, mixed>
     */
    protected function segmentRules(): array
    {
        return [
            'segment' => ['array'],
            'segment.role' => ['nullable', Rule::in(UserRole::values())],
            'segment.status' => ['nullable', Rule::in(UserStatus::values())],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function segmentRole(array $validated): ?string
    {
        return isset($validated['segment']['role'])
            ? (string) $validated['segment']['role']
            : null;
    }
}
