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
 * POS. Sent on-demand (the invitee has no account yet) and delivered through the
 * workspace's dashboard SMTP via {@see RendersWorkspaceMail}.
 */
class CashierInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable, RendersWorkspaceMail;

    public function __construct(
        public readonly Workspace $workspace,
        public readonly string $token,
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
        $url = rtrim((string) config('app.frontend_url'), '/')
            .'/cashier/accept?token='.urlencode($this->token);

        $greeting = $this->name !== null ? "مرحباً {$this->name}،" : 'مرحباً،';

        $lines = [
            $greeting,
            "تمت دعوتك للانضمام كموظف كاشير في {$name} على منصة طاقات.",
            "You have been invited to join {$name} as a POS (café) staff member.",
            'لإكمال التسجيل وتعيين كلمة المرور، افتح الرابط التالي:',
            $url,
            'الرابط صالح لمدة 7 أيام. / This link is valid for 7 days.',
        ];

        return $this->workspaceMail(
            $this->workspace,
            'دعوة للانضمام كموظف كاشير / Cashier invitation',
            $lines,
        );
    }
}
