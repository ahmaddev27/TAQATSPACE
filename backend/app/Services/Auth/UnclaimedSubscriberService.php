<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Audit\AuditLogger;
use App\Support\PhoneNumber;
use DomainException;

/**
 * Creates a subscriber that has no SSO account yet (file import, device
 * discovery, guest registration). The record carries no password and no
 * `sso_sub`, so it can never sign in; {@see ClaimService} merges it into the
 * real identity on the first SSO login.
 */
class UnclaimedSubscriberService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{name: string, email?: ?string, phone?: ?string}  $data
     *
     * @throws DomainException when no identifier is given or the email is taken
     */
    public function create(array $data): User
    {
        $email = filled($data['email'] ?? null) ? mb_strtolower(trim((string) $data['email'])) : null;
        $phone = PhoneNumber::normalize($data['phone'] ?? null);

        if ($email === null && $phone === null) {
            throw new DomainException('An unclaimed subscriber needs an email or a phone number.');
        }

        if ($email !== null && $this->users->findByEmail($email) !== null) {
            throw new DomainException('A user with this email already exists.');
        }

        $user = $this->users->create([
            'name' => $data['name'],
            'email' => $email,
            'phone' => $phone,
            'password' => null,
            'role' => UserRole::Freelancer->value,
            'status' => UserStatus::Active->value,
            'sso_sub' => null,
            'claimed_at' => null,
            'onboarding_completed_at' => null,
        ]);

        $user->assignRole(UserRole::Freelancer->value);

        $this->audit->log('subscriber.unclaimed.created', $user);

        return $user;
    }
}
