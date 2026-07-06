<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\PosOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Alerts a workspace's POS staff (the owner + active cashiers) that a freelancer
 * placed a new café order, so it gets prepared without them watching the queue.
 * Only fired for freelancer-placed orders — a cashier's own walk-in ring-up
 * needs no alert.
 */
class PosNewOrderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly PosOrder $order,
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
            'type' => 'pos_new_order',
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'workspace_id' => $this->order->workspace_id,
            // Body source for the bell (resolveBody reads `workspace_name`); who
            // placed it is carried separately.
            'workspace_name' => $this->order->workspace?->name,
            'customer' => $this->order->member?->name ?? $this->order->customer_name,
        ];
    }
}
