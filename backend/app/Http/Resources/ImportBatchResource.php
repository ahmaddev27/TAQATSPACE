<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ImportBatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ImportBatch
 */
class ImportBatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $errors = $this->errors ?? [];

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'row_count' => $this->row_count,
            'error_count' => count($errors),
            'errors' => array_slice($errors, 0, 200),
            'can_commit' => $this->status->value === 'previewed',
            'committed_at' => $this->committed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
