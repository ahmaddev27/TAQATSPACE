<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | نصوص الإشعارات الفورية (FCM)
    |--------------------------------------------------------------------------
    | عنوان/نص الإشعار الفوري المُشتق من نوع كل إشعار داخل التطبيق. مفهرس حسب
    | حقل `type` في حمولة الإشعار، مع مدخل `default` كبديل عند غياب نص مخصّص.
    */
    'push' => [
        'default' => [
            'title' => 'إشعار جديد',
        ],
        'pos_new_order' => [
            'title' => 'طلب كافيه جديد',
        ],
        'pos_order_preparing' => [
            'title' => 'طلبك قيد التحضير',
        ],
        'pos_order_ready' => [
            'title' => 'طلبك جاهز',
        ],
        'pos_order_paid' => [
            'title' => 'تم دفع طلبك',
        ],
        'pos_order_completed' => [
            'title' => 'تم تسليم طلبك',
        ],
        'pos_order_refunded' => [
            'title' => 'تم استرجاع طلبك',
        ],
        'new_message' => [
            'title' => 'رسالة جديدة',
        ],
        'new_chat_message' => [
            'title' => 'رسالة محادثة جديدة',
        ],
        'new_announcement' => [
            'title' => 'إعلان جديد',
        ],
        'invoice_created' => [
            'title' => 'فاتورة جديدة',
            'body' => 'تم إصدار الفاتورة :number (:amount).',
        ],
        'invoice_paid' => [
            'title' => 'تم استلام الدفعة',
            'body' => 'تم وضع علامة مدفوعة على الفاتورة :number.',
        ],
        'invoice_overdue' => [
            'title' => 'فاتورة متأخرة',
            'body' => 'الفاتورة :number متأخرة (:amount).',
        ],
        'invoice_reminder' => [
            'title' => 'تذكير بالفاتورة',
            'body' => 'موعد استحقاق الفاتورة :number قريب (:amount).',
        ],
        'seat_assigned' => [
            'title' => 'تم تخصيص مقعد',
            'body' => 'تم تخصيص المقعد :seat في :workspace لك.',
        ],
        'booking_approved' => [
            'title' => 'تمت الموافقة على الحجز',
            'body' => 'تمت الموافقة على حجزك في :workspace.',
        ],
        'booking_rejected' => [
            'title' => 'تم رفض الحجز',
            'body' => 'تم رفض حجزك في :workspace.',
        ],
        'new_booking_request' => [
            'title' => 'طلب حجز جديد',
            'body' => 'قدّم :name طلب حجز في :workspace.',
        ],
        'new_workspace_registration' => [
            'title' => 'تسجيل مساحة عمل جديدة',
            'body' => 'قام :name بتسجيل مساحة عمل جديدة.',
        ],
    ],

];
