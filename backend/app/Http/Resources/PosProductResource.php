<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PosProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PosProduct
 */
class PosProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'sku' => $this->sku,
            'price' => (string) $this->price,
            'track_stock' => $this->track_stock,
            'stock_qty' => $this->stock_qty,
            'is_active' => $this->is_active,
            'is_sellable' => $this->isSellable(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
