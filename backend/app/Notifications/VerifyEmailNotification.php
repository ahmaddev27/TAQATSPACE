<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailNotification extends VerifyEmail implements ShouldQueue
{
    /**
     * @param  object  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        $url = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('تحقق من بريدك الإلكتروني / Verify your email')
            ->greeting('مرحباً بك في TAQAT / Welcome to TAQAT')
            ->line('يرجى تأكيد بريدك الإلكتروني بالضغط على الزر أدناه.')
            ->line('Please verify your email address by clicking the button below.')
            ->action('تأكيد البريد / Verify Email', $url)
            ->line('إن لم تُنشئ هذا الحساب، تجاهل هذه الرسالة.')
            ->line('If you did not create an account, no further action is required.');
    }
}
