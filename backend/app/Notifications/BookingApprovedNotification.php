<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\BookingRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingApprovedNotification extends Notification implements ShouldQueue
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
        return (new MailMessage)
            ->subject('تمت الموافقة على طلب الحجز / Booking approved')
            ->line("تمت الموافقة على طلبك للانضمام إلى {$this->workspaceName}.")
            ->line("Your booking request to join {$this->workspaceName} has been approved.");
    }

    /**
     * @param  object  $notifiable
     * @return array<string, mixed>
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => 'booking_approved',
            'booking_request_id' => $this->bookingRequest->id,
            'workspace_id' => $this->bookingRequest->workspace_id,
            'workspace_name' => $this->workspaceName,
        ];
    }
}
