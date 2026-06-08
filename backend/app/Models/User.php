<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AdminRole;
use App\Enums\Gender;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, HasUuids, Notifiable;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'gender',
        'role',
        'status',
        'sso_sub',
        'onboarding_completed_at',
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
            'onboarding_completed_at' => 'datetime',
            'password' => 'hashed',
            'gender' => Gender::class,
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

    /** @return HasMany<DeviceToken, $this> */
    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
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

    /**
     * Whether this account holds the elevated `super_admin` Spatie role (full
     * control, including administering other admins). The `users.role` column
     * stays `admin` for every staff account; the super/standard split is carried
     * by Spatie roles, so this is the source of truth for the elevated tier.
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole(AdminRole::SuperAdmin->value);
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

    /**
     * Whether the user still has to pick a role and complete role-specific data.
     * True only for freshly-provisioned SSO accounts that have not onboarded yet.
     */
    public function needsOnboarding(): bool
    {
        return $this->onboarding_completed_at === null;
    }

    // ---- Notifications (SPA-aware, queued) ----

    /**
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
