<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ApprovalRequest;
use App\Services\Approvals\ApprovalHandlerRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ApprovalRequest
 */
class ApprovalRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'workspace' => $this->whenLoaded('workspace', fn () => [
                'id' => $this->workspace->id,
                'name' => $this->workspace->name,
            ]),
            'requester' => $this->whenLoaded('requester', fn () => $this->requester === null ? null : [
                'id' => $this->requester->id,
                'name' => $this->requester->name,
                'email' => $this->requester->email,
            ]),
            'change' => app(ApprovalHandlerRegistry::class)->for($this->type)->describe($this->resource),
            'decision_reason' => $this->decision_reason,
            'decided_at' => $this->decided_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
