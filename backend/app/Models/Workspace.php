<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WorkspaceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Workspace extends Model
{
    /** @use HasFactory<\Database\Factories\WorkspaceFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'owner_id',
        'name',
        'description',
        'address',
        'city',
        'phone',
        'latitude',
        'longitude',
        'total_seats',
        'price_per_month',
        'amenities',
        'photos',
        'working_hours',
        'messaging',
        'status',
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

    // ---- Query scopes (T024) ----

    /**
     * Only publicly visible (approved) workspaces.
     *
     * @param  Builder<Workspace>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', WorkspaceStatus::Active->value);
    }

    /**
     * @param  Builder<Workspace>  $query
     */
    public function scopeInCity(Builder $query, string $city): void
    {
        $query->where('city', $city);
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
