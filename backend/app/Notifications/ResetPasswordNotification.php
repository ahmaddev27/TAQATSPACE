<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword implements ShouldQueue
{
    /**
     * Point the reset link at the Next.js SPA reset page (not a backend route).
     *
     * @param  object  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        $url = rtrim((string) config('app.frontend_url'), '/')
            .'/reset-password?token='.$this->token
            .'&email='.urlencode($notifiable->getEmailForPasswordReset());

        return (new MailMessage)
            ->subject('إعادة تعيين كلمة المرور / Reset your password')
            ->greeting('مرحباً / Hello')
            ->line('لقد تلقينا طلباً لإعادة تعيين كلمة المرور لحسابك.')
            ->line('We received a request to reset your account password.')
            ->action('إعادة التعيين / Reset Password', $url)
            ->line('ينتهي هذا الرابط خلال 60 دقيقة. / This link expires in 60 minutes.')
            ->line('إن لم تطلب ذلك، تجاهل هذه الرسالة. / If you did not request this, ignore this email.');
    }
}
