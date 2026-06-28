<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Invoice;
use App\Notifications\Concerns\RendersWorkspaceMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable, RendersWorkspaceMail;

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
        $this->invoice->loadMissing('subscription.workspace.owner');

        return $this->workspaceMail(
            $this->invoice->subscription?->workspace,
            'فاتورة جديدة / New invoice',
            [
                "تم إصدار فاتورة جديدة رقم {$this->invoice->invoice_number}.",
                "A new invoice {$this->invoice->invoice_number} has been issued.",
                "المبلغ / Amount: ₪{$this->invoice->amount}",
                "تاريخ الاستحقاق / Due date: {$this->invoice->due_date?->toDateString()}",
            ],
        );
    }

    /**
     * @param  object  $notifiable
     * @return array<string, mixed>
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => 'invoice_created',
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'amount' => $this->invoice->amount,
            'due_date' => $this->invoice->due_date?->toDateString(),
        ];
    }
}
