<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\AuditActorType;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Audit\AuditLogger;
use App\Support\PhoneNumber;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Merges an unclaimed subscriber into the SSO identity signing in for the first
 * time, so its subscriptions and invoices stay attached to the same record.
 *
 * Matching order: verified email, then verified phone. An email is trusted
 * unless the IdP says it is unverified; a phone must be explicitly verified.
 */
class ClaimService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $claims  OIDC userinfo claims
     * @return ?User the merged user, or null when nothing matched (or it was claimed concurrently)
     *
     * @throws ClaimConflictException when the email and phone point at different records
     */
    public function claimOnSsoLogin(array $claims): ?User
    {
        $email = $this->verifiedEmail($claims);
        $byEmail = $email !== null ? $this->users->findUnclaimedByEmail($email) : null;
        $byPhone = $this->phoneCandidates($claims);

        if ($byEmail !== null) {
            $others = $byPhone->reject(fn (User $user): bool => $user->is($byEmail));

            return $others->isEmpty()
                ? $this->merge($byEmail, (string) $claims['sub'], 'email')
                : $this->conflict($claims, $others->push($byEmail), true);
        }

        if ($byPhone->count() > 1) {
            return $this->conflict($claims, $byPhone, false);
        }

        $match = $byPhone->first();

        return $match instanceof User ? $this->merge($match, (string) $claims['sub'], 'phone') : null;
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function verifiedEmail(array $claims): ?string
    {
        $email = $claims['email'] ?? null;

        if (! is_string($email) || trim($email) === '' || ($claims['email_verified'] ?? true) === false) {
            return null;
        }

        return mb_strtolower(trim($email));
    }

    /**
     * @param  array<string, mixed>  $claims
     * @return Collection<int, User>
     */
    private function phoneCandidates(array $claims): Collection
    {
        $raw = $claims['phone_number'] ?? $claims['phone'] ?? null;
        $phone = is_string($raw) ? PhoneNumber::normalize($raw) : null;
        $verified = ($claims['phone_number_verified'] ?? $claims['phone_verified'] ?? false) === true;

        return $phone !== null && $verified ? $this->users->findUnclaimedByPhone($phone) : new Collection;
    }

    private function merge(User $user, string $sub, string $matchedBy): ?User
    {
        $claimed = $this->users->claim($user, [
            'sso_sub' => $sub,
            'claimed_at' => now(),
            'email_verified_at' => now(),
            // Keeps password-based code paths valid; local login stays impossible.
            'password' => Hash::make(Str::random(64)),
        ]);

        if (! $claimed) {
            return null;
        }

        $this->audit->log(
            'subscriber.claimed',
            $user,
            changes: ['matched_by' => $matchedBy, 'sso_sub' => $sub],
            actorType: AuditActorType::System,
        );

        return $user->refresh();
    }

    /**
     * @param  array<string, mixed>  $claims
     * @param  Collection<int, User>  $candidates
     */
    private function conflict(array $claims, Collection $candidates, bool $blocking): ?User
    {
        $this->audit->log(
            'subscriber.claim.conflict',
            $candidates->first(),
            reason: 'Multiple unclaimed subscribers match the SSO identity; not merged automatically.',
            changes: [
                'sso_sub' => (string) $claims['sub'],
                'candidate_ids' => $candidates->pluck('id')->unique()->values()->all(),
            ],
            actorType: AuditActorType::System,
        );

        // A matching email would collide with a fresh provision, so stop the sign-in;
        // a phone-only conflict can safely fall through to a new account.
        if ($blocking) {
            throw new ClaimConflictException('Unclaimed subscriber conflict.');
        }

        return null;
    }
}
