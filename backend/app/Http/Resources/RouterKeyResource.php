<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\Router\RouterWithKey;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The only place a router key leaves the server in plaintext.
 *
 * @property RouterWithKey $resource
 */
class RouterKeyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $router = $this->resource->router;

        return [
            'id' => $router->getKey(),
            'key' => $this->resource->plainKey,
            'previous_key_expires_at' => $router->previous_key_expires_at?->toIso8601String(),
        ];
    }
}
