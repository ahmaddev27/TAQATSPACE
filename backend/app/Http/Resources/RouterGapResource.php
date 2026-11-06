<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\RouterGap;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RouterGap */
class RouterGapResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'router_id' => $this->router_id,
            'started_at' => $this->started_at->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'reason' => $this->reason->value,
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'is_open' => $this->ended_at === null,
        ];
    }
}
