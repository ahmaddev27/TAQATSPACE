<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly TaqatSsoService $sso,
    ) {}

    /**
     * Verify credentials. Returns the user on success, null on failure.
     * Status checks are handled by the caller so it can return precise responses.
     */
    public function attempt(string $email, string $password): ?User
    {
        $user = $this->users->findByEmail($email);

        if ($user === null || ! Hash::check($password, $user->password)) {
            return null;
        }

        return $user;
    }

    public function issueToken(User $user): string
    {
        return $user->createToken('auth_token')->plainTextToken;
    }

    /**
     * Revoke the current access token. When that token was issued through a
     * Taqat SSO sign-in, also return the IdP RP-initiated logout URL so the
     * caller can end the upstream SSO session; null otherwise.
     */
    public function logout(User $user): ?string
    {
        $token = $user->currentAccessToken();

        $ssoLogoutUrl = $token !== null
            ? $this->sso->buildLogoutUrl($token->getKey())
            : null;

        $token?->delete();

        return $ssoLogoutUrl;
    }
}
