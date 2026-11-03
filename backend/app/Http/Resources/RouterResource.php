<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Router;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Never exposes key material. Needs the `workspace` relation loaded.
 *
 * @mixin Router
 */
class RouterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $code = (string) $this->workspace->code;

        return [
            'id' => $this->getKey(),
            'name' => $this->name,
            'status' => $this->connectionStatus()->value,
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'serial_number' => $this->serial_number,
            'routeros_version' => $this->routeros_version,
            'script_version' => $this->script_version,
            'mode' => $this->mode,
            'workspace_code' => $code,
            'endpoint' => rtrim((string) config('services.router.sync_url'), '/').'/api/v1/hotspot/'.$code.'/sync',
            'commands' => RouterCommandResource::collection($this->whenLoaded('commands')),
        ];
    }
}
