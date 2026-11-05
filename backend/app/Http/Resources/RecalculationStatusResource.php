<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\Network\RecalculationStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RecalculationStatus */
class RecalculationStatusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [...$this->resource->toArray(), 'active' => $this->resource->isActive()];
    }
}
