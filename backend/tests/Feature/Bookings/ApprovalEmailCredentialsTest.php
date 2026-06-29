<?php

declare(strict_types=1);

namespace Tests\Feature\Bookings;

use App\Enums\SubscriptionStatus;
use App\Models\BookingRequest;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\BookingApprovedNotification;
use App\Services\MessagingSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The booking-approval email is branded (sent through the dashboard SMTP) and
 * carries the member's internet username + password.
 */
class ApprovalEmailCredentialsTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_email_includes_the_internet_credentials_via_dashboard_smtp(): void
    {
        app(MessagingSettingsService::class)->updatePlatform([
            'smtp' => [
                'host' => 'smtp.example.test',
                'port' => 587,
                'username' => 'mailer@example.test',
                'password' => 'secret',
                'encryption' => 'tls',
                'from_address' => 'no-reply@example.test',
                'from_name' => 'Platform',
            ],
        ]);

        $workspace = Workspace::factory()->create();
        $member = User::factory()->freelancer()->create();
        $subscription = Subscription::factory()->create([
            'workspace_id' => $workspace->id,
            'member_id' => $member->id,
            'status' => SubscriptionStatus::Active->value,
            'internet_username' => 'mem1234',
            'internet_password_enc' => 'pw7h2k9q',
        ]);
        $booking = BookingRequest::factory()->create([
            'workspace_id' => $workspace->id,
            'member_id' => $member->id,
        ]);

        $mail = (new BookingApprovedNotification($booking, $workspace, $subscription))
            ->toMail($member);
        $html = $mail->render();

        $this->assertSame('smtp', $mail->mailer);
        $this->assertStringContainsString('mem1234', $html);
        $this->assertStringContainsString('pw7h2k9q', $html);
        $this->assertStringContainsString($workspace->name, $html);
    }
}
