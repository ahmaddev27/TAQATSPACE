<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\StaffGroup;
use App\Models\CashierInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CashierInvitation
 */
class CashierInvitationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->name,
            'staff_group' => ($this->staff_group ?? StaffGroup::Cashier)->value,
            'permissions' => $this->permissions ?? [],
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
