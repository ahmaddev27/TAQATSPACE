<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\HourPackagePurchase;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin HourPackagePurchase
 */
class HourPurchaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'hours' => $this->hours,
            'price' => $this->price,
            'currency' => $this->currency,
            'payment_method' => $this->payment_method,
            'receipt_url' => $this->receipt_path ? MediaUrl::resolve($this->receipt_path) : null,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'activated_at' => $this->activated_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'package' => new HourPackageResource($this->whenLoaded('package')),
        ];
    }
}
