<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\HourPackage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin HourPackage
 */
class HourPackageResource extends JsonResource
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
            'hours' => $this->hours,
            'price' => $this->price,
            'currency' => $this->currency,
            'validity_days' => $this->validity_days,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
        ];
    }
}
