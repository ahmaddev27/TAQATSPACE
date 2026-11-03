<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly UserRepositoryInterface $users,
    ) {}

    /**
     * Always returns without revealing whether the email exists.
     */
    public function sendResetLink(string $email): void
    {
        // An unclaimed subscriber has no credentials; a reset would hand it a login.
        if ($this->users->findUnclaimedByEmail($email) !== null) {
            return;
        }

        Password::sendResetLink(['email' => $email]);
    }

    /**
     * @param  array<string, string>  $credentials  token, email, password, password_confirmation
     * @return string  one of Password::* status constants
     */
    public function reset(array $credentials): string
    {
        return Password::reset($credentials, function (User $user, string $password): void {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();

            // Invalidate all existing Sanctum tokens for security.
            $user->tokens()->delete();

            event(new PasswordReset($user));

            // The reset link is the proof of identity, so the account itself is the actor.
            $this->audit->log('auth.password_reset', $user, actor: $user);
        });
    }
}
