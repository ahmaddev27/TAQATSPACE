<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, HasUuids, Notifiable;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'role',
        'status',
        'avatar',
        'specialty',
        'bio',
        'documents',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
        'documents',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
            'documents' => 'array',
        ];
    }

    // ---- Relationships ----

    /** @return HasOne<Workspace, $this> */
    public function workspace(): HasOne
    {
        return $this->hasOne(Workspace::class, 'owner_id');
    }

    /** @return HasMany<Subscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'member_id');
    }

    /** @return HasMany<BookingRequest, $this> */
    public function bookingRequests(): HasMany
    {
        return $this->hasMany(BookingRequest::class, 'member_id');
    }

    /** @return HasMany<Review, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'member_id');
    }

    /** @return HasMany<Seat, $this> */
    public function assignedSeats(): HasMany
    {
        return $this->hasMany(Seat::class, 'assigned_member_id');
    }

    /** @return BelongsToMany<InternetPackage, $this> */
    public function internetPackages(): BelongsToMany
    {
        return $this->belongsToMany(InternetPackage::class, 'member_package', 'member_id', 'package_id')
            ->withPivot('assigned_at')
            ->withTimestamps();
    }

    // ---- Helpers ----

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isOwner(): bool
    {
        return $this->role === UserRole::WorkspaceOwner;
    }

    public function isFreelancer(): bool
    {
        return $this->role === UserRole::Freelancer;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    // ---- Notifications (SPA-aware, queued) ----

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    /**
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
