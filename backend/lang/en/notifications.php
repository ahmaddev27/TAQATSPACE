<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Push (FCM) notification copy
    |--------------------------------------------------------------------------
    | Title/body shown in native push notifications, derived from each stored
    | in-app notification's type. Keyed by the notification payload `type`.
    | A `default` entry is used as a fallback when a type has no specific copy.
    */
    'push' => [
        'default' => [
            'title' => 'New notification',
        ],
        'pos_new_order' => [
            'title' => 'New café order',
        ],
        'pos_order_preparing' => [
            'title' => 'Your order is being prepared',
        ],
        'pos_order_ready' => [
            'title' => 'Your order is ready',
        ],
        'pos_order_paid' => [
            'title' => 'Your order is paid',
        ],
        'pos_order_completed' => [
            'title' => 'Your order was delivered',
        ],
        'pos_order_refunded' => [
            'title' => 'Your order was refunded',
        ],
        'new_message' => [
            'title' => 'New message',
        ],
        'new_chat_message' => [
            'title' => 'New chat message',
        ],
        'new_announcement' => [
            'title' => 'New announcement',
        ],
        'invoice_created' => [
            'title' => 'New invoice',
            'body' => 'Invoice :number was issued (:amount).',
        ],
        'invoice_paid' => [
            'title' => 'Payment received',
            'body' => 'Invoice :number has been marked as paid.',
        ],
        'invoice_overdue' => [
            'title' => 'Invoice overdue',
            'body' => 'Invoice :number is overdue (:amount).',
        ],
        'invoice_reminder' => [
            'title' => 'Invoice reminder',
            'body' => 'Invoice :number is due soon (:amount).',
        ],
        'seat_assigned' => [
            'title' => 'Seat assigned',
            'body' => 'Seat :seat at :workspace was assigned to you.',
        ],
        'booking_approved' => [
            'title' => 'Booking approved',
            'body' => 'Your booking at :workspace was approved.',
        ],
        'booking_rejected' => [
            'title' => 'Booking declined',
            'body' => 'Your booking at :workspace was declined.',
        ],
        'new_booking_request' => [
            'title' => 'New booking request',
            'body' => ':name requested to book :workspace.',
        ],
        'new_workspace_registration' => [
            'title' => 'New workspace registration',
            'body' => ':name registered a new workspace.',
        ],
    ],

];
