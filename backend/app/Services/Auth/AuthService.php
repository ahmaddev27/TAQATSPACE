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

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }
}
