<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Workspace;
use App\Notifications\Concerns\RendersWorkspaceMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Emails a café/cashier staff member a branded invitation to join a workspace's
 * POS. The invitee signs in to Taqat via SSO (their email is verified by the
 * IdP) and accepts the invitation from the onboarding flow — no token is
 * embedded. Delivered through the workspace's dashboard SMTP via
 * {@see RendersWorkspaceMail}.
 */
class CashierInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable, RendersWorkspaceMail;

    public function __construct(
        public readonly Workspace $workspace,
        public readonly ?string $name = null,
    ) {}

    /**
     * @param  object  $notifiable
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    /**
     * @param  object  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        $name = $this->workspace->name;
        $url = rtrim((string) config('app.frontend_url'), '/').'/login';

        $greeting = $this->name !== null ? "مرحباً {$this->name}،" : 'مرحباً،';

        $lines = [
            $greeting,
            "تمت دعوتك للانضمام كموظف كاشير في {$name} على منصة طاقات.",
            "You have been invited to join {$name} as a POS (café) staff member.",
            'سجّل الدخول إلى طاقات بنفس بريدك الإلكتروني، ثم اقبل الدعوة من خطوة الإعداد (Onboarding).',
            'Sign in to Taqat with this email address, then accept the invitation from the onboarding step.',
            $url,
            'الدعوة صالحة لمدة 7 أيام. / This invitation is valid for 7 days.',
        ];

        return $this->workspaceMail(
            $this->workspace,
            'دعوة للانضمام كموظف كاشير / Cashier invitation',
            $lines,
        );
    }
}
