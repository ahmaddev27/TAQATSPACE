<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ApprovalRequest;
use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a platform admin allowed to decide requests that a workspace owner
 * submitted a change awaiting approval.
 */
class ApprovalRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ApprovalRequest $request,
        public readonly Workspace $workspace,
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
        $type = $this->request->type->value;

        return (new MailMessage)
            ->subject('طلب موافقة جديد / New approval request')
            ->line("قدّمت مساحة \"{$name}\" طلب تغيير ({$type}) بانتظار موافقتك.")
            ->line("Workspace \"{$name}\" submitted a change request ({$type}) awaiting your approval.");
    }

    /**
     * @param  object  $notifiable
     * @return array<string, mixed>
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => 'approval_requested',
            'approval_request_id' => $this->request->id,
            'approval_type' => $this->request->type->value,
            'workspace_id' => $this->workspace->id,
            'workspace_name' => $this->workspace->name,
        ];
    }
}
