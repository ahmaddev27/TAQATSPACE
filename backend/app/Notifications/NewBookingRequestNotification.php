<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\BookingRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a workspace owner that a freelancer has submitted a new booking request
 * for their space, so they can review (approve/reject) it without delay.
 */
class NewBookingRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly BookingRequest $bookingRequest,
        public readonly string $memberName,
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
            ->subject('طلب حجز جديد / New booking request')
            ->line("قدّم {$this->memberName} طلب حجز في {$this->workspaceName}.")
            ->line("{$this->memberName} submitted a booking request for {$this->workspaceName}.");
    }

    /**
     * @param  object  $notifiable
     * @return array<string, mixed>
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => 'new_booking_request',
            'booking_request_id' => $this->bookingRequest->id,
            'workspace_id' => $this->bookingRequest->workspace_id,
            'workspace_name' => $this->workspaceName,
            'member_name' => $this->memberName,
        ];
    }
}
