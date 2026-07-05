<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PosOrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PosOrderItem
 */
class PosOrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->pos_product_id,
            'name' => $this->name,
            'unit_price' => (string) $this->unit_price,
            'qty' => $this->qty,
            'line_total' => (string) $this->line_total,
        ];
    }
}
