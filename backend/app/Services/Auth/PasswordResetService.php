<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetService
{
    /**
     * Always returns without revealing whether the email exists.
     */
    public function sendResetLink(string $email): void
    {
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
        });
    }
}
