<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\RouterCommand;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Deliberately omits the payload: it can carry hotspot passwords and router keys.
 *
 * @mixin RouterCommand
 */
class RouterCommandResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'verb' => $this->verb->value,
            'status' => $this->status->value,
            'attempts' => $this->attempts,
            'error' => $this->error,
            'created_at' => $this->created_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'acknowledged_at' => $this->acknowledged_at?->toIso8601String(),
        ];
    }
}
