<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
     * Map stored photo paths to public URLs. Seed placeholder strings that are
     * not real disk objects are passed through unchanged.
     *
     * @return array<int, string>
     */
    private function photoUrls(): array
    {
        $disk = Storage::disk((string) config('filesystems.media', 'public'));

        return array_map(
            static fn (string $path): string => $disk->exists($path)
                ? $disk->url($path)
                : $path,
            $this->photos ?? [],
        );
    }
}
