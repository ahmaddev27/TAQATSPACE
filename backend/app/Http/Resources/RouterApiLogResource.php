<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\RouterApiLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The log only stores path, sizes and our own error message, never a request
 * body or the API key; this resource keeps it that way by listing its fields
 * explicitly.
 *
 * @mixin RouterApiLog
 */
class RouterApiLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'path' => $this->path,
            'response_code' => $this->response_code,
            'body_size' => $this->body_size,
            'duration_ms' => $this->duration_ms,
            'error_message' => $this->error_message,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
