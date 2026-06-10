<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\AccountStatusChangedNotification;
use App\Notifications\Channels\SafeMailChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\TestCase;

class SafeMailChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_mail_notification_channel_is_the_resilient_implementation(): void
    {
        $channel = app(ChannelManager::class)->driver('mail');

        $this->assertInstanceOf(SafeMailChannel::class, $channel);
    }

    public function test_an_email_failure_still_persists_the_in_app_notification(): void
    {
        // Route mail through a transport that always throws (simulating an SMTP
        // rejection like an unverified sender).
        Mail::extend('failing', static fn () => new class extends AbstractTransport
        {
            protected function doSend(SentMessage $message): void
            {
                throw new RuntimeException('Simulated SMTP 550');
            }

            public function __toString(): string
            {
                return 'failing://local';
            }
        });
        config([
            'mail.default' => 'failing',
            'mail.mailers.failing' => ['transport' => 'failing'],
        ]);

        $user = User::factory()->create();

        // Must not throw despite the mail transport failing...
        $user->notify(new AccountStatusChangedNotification(UserStatus::Suspended));

        // ...and the database (in-app) notification must still be stored, exactly once.
        $this->assertDatabaseCount('notifications', 1);
    }
}
