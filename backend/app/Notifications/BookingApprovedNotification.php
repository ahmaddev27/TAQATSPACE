<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\BookingRequest;
use App\Models\Subscription;
use App\Models\Workspace;
use App\Notifications\Concerns\RendersWorkspaceMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable, RendersWorkspaceMail;

    public function __construct(
        public readonly BookingRequest $bookingRequest,
        public readonly Workspace $workspace,
        public readonly Subscription $subscription,
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
     * Branded approval email (sent through the workspace's dashboard SMTP) that
     * also hands the member their internet login when one was provisioned.
     *
     * @param  object  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        $name = $this->workspace->name;

        $lines = [
            "تمت الموافقة على طلبك للانضمام إلى {$name}.",
            "Your booking request to join {$name} has been approved.",
        ];

        if ($this->subscription->internet_username !== null) {
            $lines[] = 'بيانات الدخول للإنترنت / Your internet login:';
            $lines[] = "اسم المستخدم / Username: {$this->subscription->internet_username}";
            $lines[] = "كلمة المرور / Password: {$this->subscription->internet_password_enc}";
        }

        return $this->workspaceMail(
            $this->workspace,
            'تمت الموافقة على طلب الحجز / Booking approved',
            $lines,
        );
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
            'workspace_id' => $this->workspace->id,
            'workspace_name' => $this->workspace->name,
        ];
    }
}
