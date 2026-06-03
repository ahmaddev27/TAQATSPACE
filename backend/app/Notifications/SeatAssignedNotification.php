<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Seat;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SeatAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Seat $seat,
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
            ->subject('تم تخصيص مقعد لك / A seat was assigned to you')
            ->line("تم تخصيص المقعد {$this->seat->seat_number} لك في {$this->workspaceName}.")
            ->line("Seat {$this->seat->seat_number} has been assigned to you at {$this->workspaceName}.");
    }

    /**
     * @param  object  $notifiable
     * @return array<string, mixed>
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => 'seat_assigned',
            'seat_id' => $this->seat->id,
            'seat_number' => $this->seat->seat_number,
            'workspace_id' => $this->seat->workspace_id,
            'workspace_name' => $this->workspaceName,
        ];
    }
}
