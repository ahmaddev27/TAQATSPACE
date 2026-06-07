<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | رسائل التطبيق
    |--------------------------------------------------------------------------
    |
    | الرسائل الموجهة للمستخدم التي تصدرها وحدات التحكم والخدمات عبر مغلّف
    | ApiResponse وحالات الإنهاء واستثناءات المجال. المفاتيح مجمّعة حسب
    | المجال لتسهيل العثور عليها.
    |
    */

    // عام / مشترك
    'unauthenticated' => 'غير مصادق.',
    'insufficient_permissions' => 'الصلاحيات غير كافية.',
    'unauthorized_action' => 'هذا الإجراء غير مصرّح به.',
    'no_workspace' => 'ليس لديك مساحة عمل.',
    'no_workspace_to_update' => 'ليس لديك مساحة عمل لتحديثها.',
    'no_workspace_to_publish' => 'ليس لديك مساحة عمل للنشر فيها.',
    'no_workspace_found_account' => 'لا توجد مساحة عمل مرتبطة بهذا الحساب.',
    'no_workspace_found_owner' => 'لا توجد مساحة عمل مرتبطة بهذا المالك.',

    // المصادقة
    'account_suspended' => 'الحساب موقوف.',
    'account_pending' => 'الحساب قيد التحقق.',
    'invalid_credentials' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة.',
    'current_password_incorrect' => 'كلمة المرور الحالية غير صحيحة.',
    'email_already_verified' => 'تم التحقق من البريد الإلكتروني مسبقًا.',
    'verification_link_sent' => 'تم إرسال رابط التحقق.',
    'invalid_verification_link' => 'رابط التحقق غير صالح.',

    // إعادة تعيين كلمة المرور
    'reset_link_sent' => 'إذا كان هذا البريد الإلكتروني موجودًا، فقد تم إرسال رابط إعادة التعيين.',
    'password_reset_success' => 'تمت إعادة تعيين كلمة المرور بنجاح.',
    'invalid_reset_token' => 'رمز إعادة التعيين غير صالح أو منتهي الصلاحية.',

    // الدخول الموحّد
    'sso_missing_code' => 'رمز التبادل مفقود.',
    'sso_invalid_code' => 'رمز التبادل غير صالح أو منتهي الصلاحية.',
    'sso_user_not_found' => 'المستخدم غير موجود.',

    // إكمال التسجيل (اختيار الدور بعد الدخول الموحّد)
    'onboarding_completed' => 'تم تجهيز حسابك.',
    'onboarding_already_completed' => 'تم إعداد حسابك بالفعل.',

    // الملف الشخصي
    'profile_updated' => 'تم تحديث الملف الشخصي بنجاح.',
    'password_changed' => 'تم تغيير كلمة المرور بنجاح.',

    // مساحة العمل
    'workspace_not_found' => 'مساحة العمل غير موجودة.',
    'workspace_created' => 'تم إنشاء مساحة العمل وإرسالها للمراجعة.',
    'workspace_updated' => 'تم تحديث مساحة العمل.',
    'workspace_status_updated' => 'تم تحديث حالة مساحة العمل.',
    'workspace_already_registered' => 'لديك مساحة عمل مسجّلة بالفعل.',

    // الصور
    'photos_uploaded' => 'تم رفع الصور.',
    'photo_removed' => 'تمت إزالة الصورة.',
    'photos_max_reached' => 'يمكن أن تحتوي مساحة العمل على :max صورة كحد أقصى.',
    'photo_not_found' => 'الصورة غير موجودة في مساحة العمل هذه.',

    // المقاعد
    'seat_deleted' => 'تم حذف المقعد.',
    'seat_type_pricing_updated' => 'تم تحديث تسعير أنواع المقاعد.',
    'seat_capacity_reached' => 'تم الوصول إلى الحد الأقصى لسعة المقاعد في مساحة العمل هذه.',
    'seat_number_exists' => 'يوجد مقعد بهذا الرقم في مساحة العمل بالفعل.',
    'seat_member_no_subscription' => 'ليس لدى العضو اشتراك نشط في مساحة العمل هذه.',
    'seat_member_already_holds' => 'العضو يحجز مقعدًا بالفعل في مساحة العمل هذه.',
    'seat_only_available_deletable' => 'يمكن حذف المقاعد المتاحة فقط.',
    'seat_referenced_by_subscription' => 'المقعد مرتبط باشتراك ولا يمكن حذفه.',
    'seat_not_in_workspace' => 'المقعد المحدد لا ينتمي إلى مساحة العمل هذه.',
    'seat_unavailable' => 'المقعد المحدد لم يعد متاحًا. يرجى اختيار مقعد آخر.',

    // الأعضاء
    'member_not_found' => 'العضو غير موجود.',
    'member_not_in_workspace' => 'العضو ليس جزءًا من مساحة العمل هذه.',
    'member_status_updated' => 'تم تحديث حالة العضو.',

    // الحجوزات / الاشتراكات
    'booking_submitted' => 'تم إرسال طلب الحجز.',
    'booking_approved' => 'تمت الموافقة على الحجز.',
    'booking_rejected' => 'تم رفض الحجز.',
    'subscription_cancelled' => 'تم إلغاء الاشتراك.',
    'booking_not_accepting' => 'مساحة العمل هذه لا تقبل طلبات الحجز.',
    'booking_already_pending' => 'لديك طلب حجز قيد الانتظار بالفعل.',
    'booking_already_subscribed' => 'لديك اشتراك نشط في مساحة العمل هذه بالفعل.',
    'booking_already_reviewed' => 'تمت مراجعة طلب الحجز هذا بالفعل.',
    'subscription_already_cancelled' => 'تم إلغاء هذا الاشتراك بالفعل.',
    'subscription_no_access' => 'ليس لديك صلاحية الوصول إلى هذا الاشتراك.',

    // الباقات
    'package_created' => 'تم إنشاء الباقة بنجاح.',
    'package_updated' => 'تم تحديث الباقة بنجاح.',
    'package_deleted' => 'تم حذف الباقة بنجاح.',
    'package_assigned' => 'تم تعيين الباقة للعضو.',
    'package_unassigned' => 'تم إلغاء تعيين الباقة من العضو.',
    'package_not_found' => 'الباقة غير موجودة.',

    // الفواتير
    'invoice_not_found' => 'الفاتورة غير موجودة.',
    'invoice_created' => 'تم إنشاء الفاتورة.',
    'invoice_marked_paid' => 'تم تعليم الفاتورة كمدفوعة.',
    'invoice_reverted_pending' => 'تمت إعادة الفاتورة إلى حالة قيد الانتظار.',
    'receipt_uploaded' => 'تم رفع الإيصال وتعليم الفاتورة كمدفوعة.',
    'reminder_sent' => 'تم إرسال التذكير.',
    'invoice_member_no_subscription' => 'هذا العضو ليس لديه اشتراك نشط في مساحة عملك.',
    'invoice_already_paid' => 'تم دفع هذه الفاتورة بالفعل.',
    'invoice_reminder_recently_sent' => 'تم إرسال تذكير لهذه الفاتورة خلال الـ 24 ساعة الماضية بالفعل.',

    // الإعلانات
    'announcement_created' => 'تم إنشاء الإعلان.',
    'announcement_updated' => 'تم تحديث الإعلان.',
    'announcement_not_found' => 'الإعلان غير موجود.',
    'announcement_deleted' => 'تم حذف الإعلان.',

    // الرسائل
    'message_sent' => 'تم إرسال الرسالة.',
    'broadcast_sent' => 'تم إرسال البث.',
    'messages_marked_read' => 'تم تعليم الرسائل كمقروءة.',
    'recipient_not_active_member' => 'المستلم ليس عضوًا نشطًا في مساحة عملك.',
    'no_active_membership' => 'لم يتم العثور على عضوية نشطة.',

    // الإشعارات
    'all_notifications_marked_read' => 'تم تعليم جميع الإشعارات كمقروءة.',
    'notifications_marked_read' => 'تم تعليم الإشعارات كمقروءة.',
    'notification_deleted' => 'تم حذف الإشعار.',
    'notification_not_found' => 'الإشعار غير موجود.',

    // التقييمات
    'review_submitted' => 'تم إرسال التقييم بنجاح.',
    'review_only_subscribed' => 'يمكنك تقييم مساحة عمل أنت مشترك فيها أو كنت مشتركًا فيها فقط.',
    'review_already_reviewed' => 'لقد قمت بتقييم مساحة العمل هذه بالفعل.',

    // محتوى الإدارة / الصفحة الرئيسية / الهوية / الإعدادات
    'content_updated' => 'تم تحديث المحتوى.',
    'landing_content_updated' => 'تم تحديث محتوى الصفحة الرئيسية.',
    'landing_image_uploaded' => 'تم رفع الصورة.',
    'branding_updated' => 'تم تحديث الهوية.',
    'branding_image_uploaded' => 'تم رفع الصورة.',
    'user_status_updated' => 'تم تحديث حالة المستخدم.',
    'messaging_settings_updated' => 'تم تحديث إعدادات المراسلة.',
    'workspace_messaging_updated' => 'تم تحديث إعدادات مراسلة مساحة العمل.',

    // اختبار المراسلة
    'test_email_sent' => 'تم إرسال بريد إلكتروني تجريبي إلى :to.',
    'test_email_failed' => 'فشل إرسال البريد الإلكتروني التجريبي: :error',
    'test_sms_sent' => 'تم إرسال رسالة نصية تجريبية إلى :to.',
    'test_sms_failed' => 'فشل إرسال الرسالة النصية التجريبية: :error',
    'sms_not_configured' => 'الرسائل النصية غير مهيّأة للمنصة.',

    // البث (إنشاء رسالة وإرسالها إلى جمهور)
    'broadcast_queued' => 'تمت جدولة رسالتك للإرسال.',
    'broadcast_recipients_required' => 'اختر مستلِمًا واحدًا على الأقل.',
    'broadcast_email_not_configured_platform' => 'البريد الإلكتروني غير مهيّأ للمنصة. يرجى إعداد SMTP قبل الإرسال.',
    'broadcast_sms_not_configured_platform' => 'الرسائل النصية غير مهيّأة للمنصة. يرجى إعداد مزوّد رسائل نصية قبل الإرسال.',
    'broadcast_email_not_configured_workspace' => 'البريد الإلكتروني غير مهيّأ لمساحة عملك. يرجى إعداد SMTP أو استخدام حساب المنصة قبل الإرسال.',
    'broadcast_sms_not_configured_workspace' => 'الرسائل النصية غير مهيّأة لمساحة عملك. يرجى إعداد مزوّد رسائل نصية أو استخدام حساب المنصة قبل الإرسال.',

];
