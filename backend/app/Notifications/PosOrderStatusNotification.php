<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\PosOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * An in-app (bell) notification to the member who owns a POS order, telling them
 * their order advanced: preparing, ready, paid, completed, or refunded. Walk-in
 * orders have no member and never trigger this.
 */
class PosOrderStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $event  one of: preparing, ready, paid, completed, refunded
     */
    public function __construct(
        public readonly PosOrder $order,
        public readonly string $event,
    ) {}

    /**
     * @param  object  $notifiable
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        return ['database'];
    }

    /**
     * @param  object  $notifiable
     * @return array<string, mixed>
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => 'pos_order_'.$this->event,
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'workspace_id' => $this->order->workspace_id,
            'status' => $this->order->status->value,
        ];
    }
}
