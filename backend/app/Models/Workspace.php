<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WorkspaceStatus;
use App\Support\MediaUrl;
use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Workspace extends Model
{
    /** @use HasFactory<WorkspaceFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'owner_id',
        'name',
        'description',
        'address',
        'city',
        'city_id',
        'phone',
        'latitude',
        'longitude',
        'total_seats',
        'price_per_month',
        'amenities',
        'photos',
        'logo_path',
        'working_hours',
        'messaging',
        'status',
        'published_at',
        'avg_rating',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amenities' => 'array',
            'photos' => 'array',
            'working_hours' => 'array',
            'messaging' => 'array',
            'status' => WorkspaceStatus::class,
            'published_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'total_seats' => 'integer',
            'price_per_month' => 'decimal:2',
            'avg_rating' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Cashier/café staff accounts that operate this workspace's POS.
     *
     * @return HasMany<User, $this>
     */
    public function cashiers(): HasMany
    {
        return $this->hasMany(User::class, 'workspace_id');
    }

    /**
     * The workspace's display logo as a resolved URL. Prefers the dedicated
     * logo; falls back to the owner's avatar (only when that relation is loaded,
     * to stay N+1-free) so workspaces without a logo keep their prior image.
     */
    public function logoUrl(): ?string
    {
        $path = $this->logo_path
            ?? ($this->relationLoaded('owner') ? $this->owner?->avatar : null);

        return MediaUrl::resolve($path);
    }

    /**
     * The related city record. Named `cityModel` (not `city`) to avoid colliding
     * with the denormalized `city` string column/attribute.
     *
     * @return BelongsTo<City, $this>
     */
    public function cityModel(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    /** @return HasMany<Seat, $this> */
    public function seats(): HasMany
    {
        return $this->hasMany(Seat::class);
    }

    /** @return HasMany<SeatTypePrice, $this> */
    public function seatTypes(): HasMany
    {
        return $this->hasMany(SeatTypePrice::class);
    }

    /** @return HasMany<Subscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /** @return HasMany<Review, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /** @return HasMany<InternetPackage, $this> */
    public function internetPackages(): HasMany
    {
        return $this->hasMany(InternetPackage::class);
    }

    /** @return HasMany<BookingRequest, $this> */
    public function bookingRequests(): HasMany
    {
        return $this->hasMany(BookingRequest::class);
    }

    /** @return HasMany<Announcement, $this> */
    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class);
    }

    /** @return HasMany<Expense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /** @return HasMany<resource, $this> */
    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class);
    }

    /**
     * Whether this workspace has been published to public discovery by an admin.
     * Independent of the account `status`: a workspace can be account-active yet
     * unpublished (and therefore hidden from the public landing/discovery).
     */
    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    // ---- Query scopes (T024) ----

    /**
     * Account-approved workspaces. NOTE: account-active does not imply publicly
     * visible — public discovery must additionally require {@see scopePublished}.
     * Use {@see scopePublic} for the combined public-visibility gate.
     *
     * @param  Builder<Workspace>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', WorkspaceStatus::Active->value);
    }

    /**
     * Workspaces an admin has published to public discovery (a non-null
     * `published_at`). Separate from the account `status` gate.
     *
     * @param  Builder<Workspace>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at');
    }

    /**
     * The single public-visibility gate: account-active AND admin-published.
     * Every public discovery path (index/search, cities, single fetch, landing
     * featured) must go through this so the rule stays DRY.
     *
     * @param  Builder<Workspace>  $query
     */
    public function scopePublic(Builder $query): void
    {
        $query->active()->published();
    }

    /**
     * @param  Builder<Workspace>  $query
     */
    public function scopeInCity(Builder $query, string $city): void
    {
        // The filter value may arrive as a city id, its Arabic name, or its
        // English name (the public explore page sends the localized label). Resolve
        // it to the city record, then match workspaces by id OR the denormalized
        // Arabic `city` string (older rows may have the string but no city_id).
        $cityModel = City::query()
            ->where('id', $city)
            ->orWhere('name_ar', $city)
            ->orWhere('name_en', $city)
            ->first();

        if ($cityModel === null) {
            $query->where('city', $city);

            return;
        }

        $query->where(function (Builder $inner) use ($cityModel): void {
            $inner->where('city_id', $cityModel->id)
                ->orWhere('city', $cityModel->name_ar);
        });
    }

    /**
     * @param  Builder<Workspace>  $query
     */
    public function scopePriceRange(Builder $query, float $min, float $max): void
    {
        $query->whereBetween('price_per_month', [$min, $max]);
    }

    /**
     * Each amenity must be present in the JSON `amenities` array (AND semantics).
     *
     * @param  Builder<Workspace>  $query
     * @param  array<int, string>  $amenities
     */
    public function scopeWithAmenities(Builder $query, array $amenities): void
    {
        foreach ($amenities as $amenity) {
            $query->whereJsonContains('amenities', $amenity);
        }
    }

    /**
     * @param  Builder<Workspace>  $query
     */
    public function scopeMinRating(Builder $query, float $rating): void
    {
        $query->where('avg_rating', '>=', $rating);
    }

    /**
     * Case-insensitive text search across name and description.
     * MySQL's default collation makes LIKE case-insensitive.
     *
     * @param  Builder<Workspace>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $like = '%'.$term.'%';

        $query->where(function (Builder $inner) use ($like): void {
            $inner->where('name', 'like', $like)
                ->orWhere('description', 'like', $like);
        });
    }
}
