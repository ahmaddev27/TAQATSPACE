<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\BookingRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly BookingRequest $bookingRequest,
        public readonly string $workspaceName,
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
        $message = (new MailMessage)
            ->subject('تم رفض طلب الحجز / Booking rejected')
            ->line("نعتذر، تم رفض طلبك للانضمام إلى {$this->workspaceName}.")
            ->line("Your booking request to join {$this->workspaceName} has been rejected.");

        if ($this->bookingRequest->rejection_reason !== null) {
            $message->line("السبب / Reason: {$this->bookingRequest->rejection_reason}");
        }

        return $message;
    }

    /**
     * @param  object  $notifiable
     * @return array<string, mixed>
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => 'booking_rejected',
            'booking_request_id' => $this->bookingRequest->id,
            'workspace_id' => $this->bookingRequest->workspace_id,
            'workspace_name' => $this->workspaceName,
            'rejection_reason' => $this->bookingRequest->rejection_reason,
        ];
    }
}
