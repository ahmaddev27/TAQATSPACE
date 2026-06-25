<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a member that the owner rejected their uploaded payment receipt, with the
 * reason, so they can fix it and upload a new one.
 */
class InvoiceReceiptRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Invoice $invoice,
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
        $number = $this->invoice->invoice_number;
        $reason = (string) $this->invoice->receipt_rejected_reason;

        return (new MailMessage)
            ->subject('وصل الدفع مرفوض / Payment receipt rejected')
            ->line("تم رفض وصل الدفع للفاتورة {$number}. السبب: {$reason}")
            ->line("يرجى رفع وصل جديد بعد التصحيح.")
            ->line("Your payment receipt for invoice {$number} was rejected. Reason: {$reason}")
            ->line('Please upload a corrected receipt.');
    }

    /**
     * @param  object  $notifiable
     * @return array<string, mixed>
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => 'invoice_receipt_rejected',
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'reason' => $this->invoice->receipt_rejected_reason,
        ];
    }
}
