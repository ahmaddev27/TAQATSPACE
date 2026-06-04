<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\SeatTypePrice;
use App\Models\Workspace;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Workspace
 */
class WorkspaceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'owner_id' => $this->owner_id,
            'name' => $this->name,
            'description' => $this->description,
            'address' => $this->address,
            'city' => $this->city,
            'phone' => $this->phone,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'total_seats' => $this->total_seats,
            'price_per_month' => $this->price_per_month,
            'amenities' => $this->amenities ?? [],
            'photos' => $this->photoUrls(),
            'working_hours' => $this->working_hours,
            'status' => $this->status->value,
            'avg_rating' => $this->avg_rating,
            'seat_types' => $this->seatTypeRows(),
            'created_at' => $this->created_at?->toIso8601String(),

            // Detail-only bundle: present when the controller attaches it.
            'seats_summary' => $this->whenHas('seats_summary'),
            'recent_reviews' => $this->whenHas('recent_reviews'),

            // Admin listing only: present when the owner relation is loaded.
            'owner' => $this->whenLoaded('owner', fn (): array => [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
                'email' => $this->owner->email,
                'phone' => $this->owner->phone,
            ]),
        ];
    }

    /**
     * The workspace's per-seat-type pricing rows in the public contract shape.
     * All rows are returned (including disabled ones); the public client hides
     * disabled types itself.
     *
     * @return array<int, array{type: string, price_monthly: ?string, price_daily: ?string, capacity: int, enabled: bool}>
     */
    private function seatTypeRows(): array
    {
        $this->resource->loadMissing('seatTypes');

        return $this->seatTypes
            ->map(static fn (SeatTypePrice $row): array => [
                'type' => $row->type->value,
                'price_monthly' => $row->price_monthly,
                'price_daily' => $row->price_daily,
                'capacity' => $row->capacity,
                'enabled' => $row->enabled,
            ])
            ->all();
    }

    /**
     * Map stored photo paths to public URLs. Seed placeholder strings that are
     * not real disk objects are passed through unchanged.
     *
     * @return array<int, string>
     */
    private function photoUrls(): array
    {
        return array_map(
            static fn (string $path): string => MediaUrl::resolve($path) ?? $path,
            $this->photos ?? [],
        );
    }
}
