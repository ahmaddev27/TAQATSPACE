<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\ApprovalStatus;
use App\Models\ApprovalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the requesting owner how their change request was decided, including the
 * rejection reason when there is one.
 */
class ApprovalDecidedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ApprovalRequest $request,
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
        $type = $this->request->type->value;
        $reason = (string) $this->request->decision_reason;

        if ($this->request->status === ApprovalStatus::Approved) {
            return (new MailMessage)
                ->subject('تمت الموافقة على طلبك / Request approved')
                ->line("تمت الموافقة على طلب التغيير ({$type}) وتم تطبيقه.")
                ->line("Your change request ({$type}) was approved and applied.");
        }

        return (new MailMessage)
            ->subject('تم رفض طلبك / Request rejected')
            ->line("تم رفض طلب التغيير ({$type}). السبب: {$reason}")
            ->line("Your change request ({$type}) was rejected. Reason: {$reason}");
    }

    /**
     * @param  object  $notifiable
     * @return array<string, mixed>
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => 'approval_decided',
            'approval_request_id' => $this->request->id,
            'approval_type' => $this->request->type->value,
            'status' => $this->request->status->value,
            'reason' => $this->request->decision_reason,
        ];
    }
}
