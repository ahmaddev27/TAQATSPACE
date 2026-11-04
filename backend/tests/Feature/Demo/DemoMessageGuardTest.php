<?php

declare(strict_types=1);

namespace Tests\Feature\Demo;

use App\Enums\UserStatus;
use App\Jobs\SendBroadcastMessage;
use App\Listeners\PushNotificationToDevices;
use App\Models\User;
use App\Notifications\AccountStatusChangedNotification;
use App\Services\Firebase\FirebaseService;
use App\Services\Sms\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DemoMessageGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_demo_account_is_recognised_by_its_address(): void
    {
        $this->assertTrue(User::factory()->make(['email' => 'x@demo.taqat.test'])->isDemo());
        $this->assertTrue(User::factory()->make(['email' => 'X@DEMO.TAQAT.TEST'])->isDemo());
        $this->assertFalse(User::factory()->make(['email' => 'x@mail.ps'])->isDemo());
        $this->assertFalse(User::factory()->make(['email' => 'x@evil-demo.taqat.test.example.com'])->isDemo());
    }

    public function test_a_demo_account_gets_the_in_app_notification_but_no_email(): void
    {
        config(['mail.default' => 'array']);
        $demo = User::factory()->create(['email' => 'someone@demo.taqat.test']);
        $real = User::factory()->create(['email' => 'real@mail.ps']);

        $demo->notify(new AccountStatusChangedNotification(UserStatus::Suspended));
        $real->notify(new AccountStatusChangedNotification(UserStatus::Suspended));

        $this->assertSame(1, $demo->notifications()->count());

        $recipients = collect(Mail::mailer('array')->getSymfonyTransport()->messages())
            ->flatMap(fn ($sent) => collect($sent->getEnvelope()->getRecipients())->map->getAddress())
            ->all();
        $this->assertSame(['real@mail.ps'], $recipients);
    }

    public function test_an_invitation_addressed_to_a_demo_mailbox_sends_no_email(): void
    {
        config(['mail.default' => 'array']);
        $notification = new class extends \Illuminate\Notifications\Notification
        {
            /** @return list<string> */
            public function via(mixed $notifiable): array
            {
                return ['mail'];
            }

            public function toMail(mixed $notifiable): MailMessage
            {
                return (new MailMessage)->subject('Invitation')->line('Hello');
            }
        };

        Notification::route('mail', 'invitee@demo.taqat.test')->notify($notification);
        Notification::route('mail', 'invitee@mail.ps')->notify($notification);

        $this->assertCount(1, Mail::mailer('array')->getSymfonyTransport()->messages());
    }

    public function test_a_demo_account_is_not_pushed_to_its_devices(): void
    {
        $firebase = $this->mock(FirebaseService::class);
        $firebase->shouldReceive('isConfigured')->andReturn(true);
        $firebase->shouldNotReceive('sendToTokens');

        $demo = User::factory()->create(['email' => 'someone@demo.taqat.test']);
        $demo->deviceTokens()->create(['token' => 'tok-1', 'platform' => 'web']);

        (new PushNotificationToDevices($firebase))->handle(
            new NotificationSent($demo, new AccountStatusChangedNotification(UserStatus::Suspended), 'database'),
        );

        $this->addToAssertionCount(1);
    }

    public function test_a_broadcast_email_to_a_demo_address_is_not_sent(): void
    {
        Mail::fake();

        (new SendBroadcastMessage('email', 'a@demo.taqat.test', 'Subject', 'Body', []))->handle(app(SmsService::class));

        Mail::assertNothingSent();
    }
}
