<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a workspace owner that a member uploaded a payment receipt, so the owner
 * can review it and confirm the invoice as paid.
 */
class InvoiceReceiptSubmittedNotification extends Notification implements ShouldQueue
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

        return (new MailMessage)
            ->subject('وصل دفع بانتظار المراجعة / Payment receipt submitted')
            ->line("تم رفع وصل دفع للفاتورة {$number} وهي الآن بانتظار مراجعتك وتأكيدها.")
            ->line("A payment receipt for invoice {$number} is awaiting your review and confirmation.");
    }

    /**
     * @param  object  $notifiable
     * @return array<string, mixed>
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => 'invoice_receipt_submitted',
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
        ];
    }
}
