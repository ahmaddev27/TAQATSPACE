<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\InvoiceCreatedNotification;
use App\Notifications\InvoiceOverdueNotification;
use App\Notifications\InvoicePaidNotification;
use App\Notifications\InvoiceReceiptRejectedNotification;
use App\Notifications\InvoiceReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The invoice notifications using RendersWorkspaceMail must render in the
 * WORKSPACE's own name (sender + header + footer) and must NOT carry the
 * platform 'TAQAT' branding/footer.
 */
class WorkspaceMailBrandingTest extends TestCase
{
    use RefreshDatabase;

    private const WORKSPACE_NAME = 'Brandkit Coworking Hub';

    private function invoiceForWorkspace(?string $logoPath = null): Invoice
    {
        $owner = User::factory()->owner()->create();

        $workspace = Workspace::factory()->create([
            'owner_id' => $owner->id,
            'name' => self::WORKSPACE_NAME,
            'logo_path' => $logoPath,
        ]);

        $subscription = Subscription::factory()->create([
            'workspace_id' => $workspace->id,
        ]);

        return Invoice::factory()->create([
            'subscription_id' => $subscription->id,
            'invoice_number' => 'BRAND-2026-0001',
            'receipt_rejected_reason' => 'Blurred image',
        ]);
    }

    private function notifiable(Invoice $invoice): User
    {
        return $invoice->subscription->member;
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function workspaceNotificationProvider(): iterable
    {
        yield 'created' => ['created'];
        yield 'paid' => ['paid'];
        yield 'overdue' => ['overdue'];
        yield 'reminder' => ['reminder'];
        yield 'receipt_rejected' => ['receipt_rejected'];
    }

    private function makeNotification(string $key, Invoice $invoice): BaseNotification
    {
        return match ($key) {
            'created' => new InvoiceCreatedNotification($invoice),
            'paid' => new InvoicePaidNotification($invoice),
            'overdue' => new InvoiceOverdueNotification($invoice, 'member'),
            'reminder' => new InvoiceReminderNotification($invoice),
            'receipt_rejected' => new InvoiceReceiptRejectedNotification($invoice),
        };
    }

    #[DataProvider('workspaceNotificationProvider')]
    public function test_sender_name_is_the_workspace_name(string $key): void
    {
        $invoice = $this->invoiceForWorkspace();
        $mail = $this->makeNotification($key, $invoice)->toMail($this->notifiable($invoice));

        $this->assertSame(self::WORKSPACE_NAME, $mail->from[1] ?? null);
        $this->assertSame(config('mail.from.address'), $mail->from[0] ?? null);
    }

    #[DataProvider('workspaceNotificationProvider')]
    public function test_rendered_html_contains_workspace_name_and_no_platform_footer(string $key): void
    {
        $invoice = $this->invoiceForWorkspace();
        $html = $this->render($this->makeNotification($key, $invoice)->toMail($this->notifiable($invoice)));

        $this->assertStringContainsString(self::WORKSPACE_NAME, $html);
        $this->assertStringNotContainsStringIgnoringCase('TAQAT', $html);
    }

    public function test_logo_is_embedded_inline_with_a_content_id(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('logos/ws-logo.png', $this->pngBytes());

        $invoice = $this->invoiceForWorkspace('logos/ws-logo.png');
        $mail = (new InvoiceCreatedNotification($invoice))->toMail($this->notifiable($invoice));
        $html = $this->render($mail);

        // The blade embeds the bytes via $message->embedData(), producing a cid: URI.
        $this->assertStringContainsString('cid:', $html);
        $this->assertStringContainsString('workspace-logo', $html);
    }

    public function test_missing_logo_file_does_not_error_and_omits_inline_image(): void
    {
        Storage::fake('public');
        // logo_path is set but the file does not exist on disk.
        $invoice = $this->invoiceForWorkspace('logos/does-not-exist.png');

        $html = $this->render((new InvoiceCreatedNotification($invoice))->toMail($this->notifiable($invoice)));

        $this->assertStringNotContainsString('cid:', $html);
        $this->assertStringContainsString(self::WORKSPACE_NAME, $html);
    }

    /**
     * Render the MailMessage's blade view to its final HTML. A fake $message
     * stand-in is injected so embedData() (used for inline logos) resolves to a
     * cid: URI exactly as the real mailer would.
     */
    private function render(MailMessage $mail): string
    {
        return view(
            $mail->view,
            $mail->viewData + ['message' => $this->fakeMessage()]
        )->render();
    }

    /**
     * A minimal stand-in for the Symfony email message exposed to the blade as
     * $message, supporting embedData() the way the mailer does.
     */
    private function fakeMessage(): object
    {
        return new class
        {
            public function embedData(string $data, string $name, ?string $contentType = null): string
            {
                return 'cid:'.$name;
            }
        };
    }

    private function pngBytes(): string
    {
        // 1x1 transparent PNG.
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        );
    }
}
