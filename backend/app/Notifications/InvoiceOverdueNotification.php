<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Invoice;
use App\Notifications\Concerns\RendersWorkspaceMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceOverdueNotification extends Notification implements ShouldQueue
{
    use Queueable, RendersWorkspaceMail;

    /**
     * @param  'member'|'owner'  $audience
     */
    public function __construct(
        public readonly Invoice $invoice,
        public readonly string $audience = 'member',
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
        $workspace = $this->invoice->subscription?->workspace;
        $subject = 'فاتورة متأخرة / Overdue invoice';

        if ($this->audience === 'owner') {
            return $this->workspaceMail($workspace, $subject, [
                "الفاتورة رقم {$this->invoice->invoice_number} أصبحت متأخرة عن السداد.",
                "Invoice {$this->invoice->invoice_number} is now overdue.",
            ]);
        }

        return $this->workspaceMail($workspace, $subject, [
            "فاتورتك رقم {$this->invoice->invoice_number} أصبحت متأخرة. يرجى السداد في أقرب وقت.",
            "Your invoice {$this->invoice->invoice_number} is overdue. Please pay as soon as possible.",
        ]);
    }

    /**
     * @param  object  $notifiable
     * @return array<string, mixed>
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => 'invoice_overdue',
            'audience' => $this->audience,
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'amount' => $this->invoice->amount,
            'due_date' => $this->invoice->due_date?->toDateString(),
        ];
    }
}
