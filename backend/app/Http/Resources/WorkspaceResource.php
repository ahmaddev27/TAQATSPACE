<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\SeatTypePrice;
use App\Models\Workspace;
use App\Services\MessagingSettingsService;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

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
            'city_id' => $this->city_id,
            'phone' => $this->phone,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'total_seats' => $this->total_seats,
            'price_per_month' => $this->price_per_month,
            'amenities' => $this->amenities ?? [],
            'photos' => $this->photoUrls(),
            // The workspace "logo" is its owner's avatar (resolved). Present only
            // when the owner relation is eager-loaded, so reads stay N+1-free.
            'logo' => $this->relationLoaded('owner')
                ? MediaUrl::resolve($this->owner?->avatar)
                : null,
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
                'avatar' => MediaUrl::resolve($this->owner->avatar),
            ]),

            // Owner/Admin-only: publish state + masked messaging config (never
            // the secrets). The public discovery payload never exposes these.
            $this->mergeWhen(
                $this->canManage($request),
                fn (): array => [
                    'is_published' => $this->isPublished(),
                    'published_at' => $this->published_at?->toIso8601String(),
                    'messaging' => $this->maskedMessaging(),
                ],
            ),
        ];
    }

    /**
     * Whether the current request may see this workspace's messaging config —
     * its owner or an admin. Public discovery requests never qualify.
     */
    private function canManage(Request $request): bool
    {
        $user = $request->user();

        return $user !== null
            && Gate::forUser($user)->allows('manage-workspace', $this->resource);
    }

    /**
     * The masked messaging block (use_platform + non-secret fields + has_* flags).
     *
     * @return array<string, mixed>
     */
    private function maskedMessaging(): array
    {
        return app(MessagingSettingsService::class)->maskedForWorkspace($this->resource);
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
