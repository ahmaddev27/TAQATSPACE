<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Workspace;
use App\Notifications\InvoiceCreatedNotification;
use App\Notifications\InvoiceOverdueNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Invoice mail rendered through RendersWorkspaceMail must read in the
 * workspace's own name: the sender name is the workspace name, the body view
 * carries the workspace name, and no TAQAT/platform footer leaks in.
 */
class WorkspaceBrandedInvoiceMailTest extends TestCase
{
    use RefreshDatabase;

    private function invoiceForWorkspace(string $name): Invoice
    {
        $workspace = Workspace::factory()->create(['name' => $name, 'logo_path' => null]);
        $subscription = Subscription::factory()->create(['workspace_id' => $workspace->id]);

        return Invoice::factory()->create([
            'subscription_id' => $subscription->id,
            'invoice_number' => 'ACME-2026-0001',
        ]);
    }

    public function test_invoice_created_mail_uses_workspace_name_as_from_name(): void
    {
        $invoice = $this->invoiceForWorkspace('Acme Space');

        $mail = (new InvoiceCreatedNotification($invoice))->toMail($invoice->subscription->member);

        $this->assertSame('Acme Space', $mail->from[1] ?? null);
        $this->assertSame((string) config('mail.from.address'), $mail->from[0] ?? null);
    }

    public function test_invoice_created_mail_renders_workspace_name_and_no_platform_footer(): void
    {
        $invoice = $this->invoiceForWorkspace('Acme Space');

        $mail = (new InvoiceCreatedNotification($invoice))->toMail($invoice->subscription->member);
        $html = view($mail->view, $mail->viewData)->render();

        $this->assertStringContainsString('Acme Space', $html);
        $this->assertStringNotContainsStringIgnoringCase('TAQAT', $html);
        $this->assertStringContainsString($invoice->invoice_number, $html);
    }

    public function test_invoice_created_mail_falls_back_to_app_name_without_workspace(): void
    {
        // A subscription with no workspace -> the trait falls back to app.name,
        // never silently dropping branding.
        $invoice = Invoice::factory()->create();
        $invoice->subscription->workspace()->dissociate();

        $mail = (new InvoiceCreatedNotification($invoice))->toMail($invoice->subscription->member);

        $this->assertSame((string) config('app.name'), $mail->from[1] ?? null);
    }

    public function test_overdue_owner_and_member_mail_carry_workspace_branding(): void
    {
        $invoice = $this->invoiceForWorkspace('Bright Hub');

        foreach (['member', 'owner'] as $audience) {
            $mail = (new InvoiceOverdueNotification($invoice, $audience))
                ->toMail($invoice->subscription->member);

            $this->assertSame('Bright Hub', $mail->from[1] ?? null);

            $html = view($mail->view, $mail->viewData)->render();
            $this->assertStringContainsString('Bright Hub', $html);
            $this->assertStringNotContainsStringIgnoringCase('TAQAT', $html);
        }
    }

    public function test_overdue_member_and_owner_bodies_differ(): void
    {
        $invoice = $this->invoiceForWorkspace('Bright Hub');
        $member = $invoice->subscription->member;

        $memberHtml = view(
            ($m = (new InvoiceOverdueNotification($invoice, 'member'))->toMail($member))->view,
            $m->viewData,
        )->render();
        $ownerHtml = view(
            ($o = (new InvoiceOverdueNotification($invoice, 'owner'))->toMail($member))->view,
            $o->viewData,
        )->render();

        $this->assertNotSame($memberHtml, $ownerHtml);
    }
}
