<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\WorkspaceStatus;
use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a workspace owner when an admin changes their workspace's moderation
 * status — approved (now active), rejected, or suspended — so the "pending
 * review" wait ends with a clear, bilingual signal. One class covers every
 * relevant transition; the copy is selected from the status.
 */
class WorkspaceStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Workspace $workspace,
        public readonly WorkspaceStatus $status,
    ) {}

    /**
     * @param  object  $notifiable
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @param  object  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        $name = $this->workspace->name;

        [$subject, $arabic, $english] = match ($this->status) {
            WorkspaceStatus::Active => [
                'تمت الموافقة على مساحتك / Workspace approved',
                "تمت الموافقة على مساحتك \"{$name}\" وأصبحت الآن نشطة.",
                "Your workspace \"{$name}\" has been approved and is now active.",
            ],
            WorkspaceStatus::Rejected => [
                'لم تتم الموافقة على مساحتك / Workspace not approved',
                "نعتذر، لم تتم الموافقة على مساحتك \"{$name}\".",
                "We're sorry — your workspace \"{$name}\" was not approved.",
            ],
            default => [
                'تم تعليق مساحتك / Workspace suspended',
                "تم تعليق مساحتك \"{$name}\". يُرجى التواصل مع الإدارة.",
                "Your workspace \"{$name}\" has been suspended. Please contact the administration.",
            ],
        };

        return (new MailMessage)
            ->subject($subject)
            ->line($arabic)
            ->line($english);
    }

    /**
     * @param  object  $notifiable
     * @return array<string, mixed>
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => 'workspace_status_changed',
            'workspace_id' => $this->workspace->id,
            'workspace_name' => $this->workspace->name,
            'status' => $this->status->value,
        ];
    }
}
