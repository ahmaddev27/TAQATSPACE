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
        'new_message' => [
            'title' => 'New message',
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
        'new_workspace_registration' => [
            'title' => 'New workspace registration',
            'body' => ':name registered a new workspace.',
        ],
    ],

];
