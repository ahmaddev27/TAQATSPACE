<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceReminderNotification extends Notification implements ShouldQueue
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
        return (new MailMessage)
            ->subject('تذكير بسداد الفاتورة / Payment reminder')
            ->line("تذكير: الفاتورة رقم {$this->invoice->invoice_number} بمبلغ ₪{$this->invoice->amount} مستحقة.")
            ->line("Reminder: invoice {$this->invoice->invoice_number} for ₪{$this->invoice->amount} is due.")
            ->line("تاريخ الاستحقاق / Due date: {$this->invoice->due_date?->toDateString()}");
    }

    /**
     * @param  object  $notifiable
     * @return array<string, mixed>
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => 'invoice_reminder',
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'amount' => $this->invoice->amount,
            'due_date' => $this->invoice->due_date?->toDateString(),
        ];
    }
}
