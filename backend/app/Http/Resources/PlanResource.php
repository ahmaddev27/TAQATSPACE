<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Plan;
use App\Services\Finance\PlanService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Plan
 */
class PlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'pricing_method' => $this->pricing_method->value,
            'price' => (string) $this->price,
            'overage_price' => $this->overage_price === null ? null : (string) $this->overage_price,
            'included_quantity' => $this->included_quantity === null ? null : (string) $this->included_quantity,
            'validity_days' => $this->validity_days,
            'is_dedicated_seat' => $this->is_dedicated_seat,
            'promo_price' => $this->promo_price === null ? null : (string) $this->promo_price,
            'promo_until' => $this->promo_until?->toIso8601String(),
            'effective_price' => app(PlanService::class)->effectivePrice($this->resource, now()),
            'is_archived' => $this->isArchived(),
            'archived_at' => $this->archived_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
