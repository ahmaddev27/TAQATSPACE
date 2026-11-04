<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\Router\IssuedInstallToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property IssuedInstallToken $resource
 */
class RouterInstallTokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'command' => $this->resource->command,
            'url' => $this->resource->url,
            'expires_at' => $this->resource->expiresAt->toIso8601String(),
        ];
    }
}
