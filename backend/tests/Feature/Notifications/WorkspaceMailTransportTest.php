<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\Invoice;
use App\Notifications\InvoiceCreatedNotification;
use App\Services\MessagingSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Workspace-branded invoice emails must be delivered through the SMTP account
 * configured in the dashboard messaging settings (DB) — not the app default
 * mailer (which is `log` when SMTP isn't set in .env).
 */
class WorkspaceMailTransportTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_email_routes_through_the_dashboard_smtp(): void
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

        $invoice = Invoice::factory()->create();

        $mail = (new InvoiceCreatedNotification($invoice))
            ->toMail($invoice->subscription->member);

        // The message is bound to the `smtp` mailer, and that mailer now carries
        // the dashboard-configured account (proving MailConfigurator applied it).
        $this->assertSame('smtp', $mail->mailer);
        $this->assertSame('smtp.example.test', config('mail.mailers.smtp.host'));
        $this->assertSame('mailer@example.test', config('mail.mailers.smtp.username'));
    }

    public function test_falls_back_to_default_mailer_when_no_smtp_configured(): void
    {
        $invoice = Invoice::factory()->create();

        $mail = (new InvoiceCreatedNotification($invoice))
            ->toMail($invoice->subscription->member);

        // Nothing configured → not forced onto the smtp mailer.
        $this->assertNotSame('smtp', $mail->mailer);
    }
}
