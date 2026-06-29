<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_reset_notification_for_known_email(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'known@mail.ps']);

        $this->postJson('/api/auth/forgot-password', ['email' => 'known@mail.ps'])
            ->assertOk();

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_forgot_password_returns_neutral_response_for_unknown_email(): void
    {
        Notification::fake();

        // Never reveal whether the email exists — still a 200.
        $this->postJson('/api/auth/forgot-password', ['email' => 'ghost@mail.ps'])
            ->assertOk();

        Notification::assertNothingSent();
    }

    public function test_forgot_password_validates_email(): void
    {
        $this->postJson('/api/auth/forgot-password', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_reset_with_valid_token_changes_password_and_revokes_tokens(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'email' => 'reset@mail.ps',
            'password' => 'old-password',
        ]);
        $user->createToken('auth_token');
        $token = Password::createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => 'reset@mail.ps',
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('brand-new-pass', $user->password));
        // All sessions invalidated after a reset.
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_reset_with_invalid_token_is_rejected(): void
    {
        User::factory()->create(['email' => 'reset2@mail.ps']);

        $this->postJson('/api/auth/reset-password', [
            'token' => 'totally-invalid-token',
            'email' => 'reset2@mail.ps',
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertStatus(422);
    }

    public function test_reset_requires_confirmed_password(): void
    {
        $this->postJson('/api/auth/reset-password', [
            'token' => 'x',
            'email' => 'reset3@mail.ps',
            'password' => 'brand-new-pass',
            'password_confirmation' => 'mismatch',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);
    }
}
