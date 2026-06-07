<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | أسطر لغة التحقق من المدخلات
    |--------------------------------------------------------------------------
    |
    | تحتوي الأسطر التالية على رسائل الخطأ الافتراضية المستخدمة من قِبل صنف
    | التحقق. بعض هذه القواعد لها أكثر من صيغة مثل قواعد الحجم. يمكنك تعديل
    | كل رسالة من هذه الرسائل هنا.
    |
    */

    'accepted' => 'يجب قبول حقل :attribute.',
    'accepted_if' => 'يجب قبول حقل :attribute عندما يكون :other هو :value.',
    'active_url' => 'يجب أن يكون حقل :attribute رابطًا صحيحًا.',
    'after' => 'يجب أن يكون حقل :attribute تاريخًا بعد :date.',
    'after_or_equal' => 'يجب أن يكون حقل :attribute تاريخًا بعد أو يساوي :date.',
    'alpha' => 'يجب أن يحتوي حقل :attribute على حروف فقط.',
    'alpha_dash' => 'يجب أن يحتوي حقل :attribute على حروف وأرقام وشرطات وشرطات سفلية فقط.',
    'alpha_num' => 'يجب أن يحتوي حقل :attribute على حروف وأرقام فقط.',
    'any_of' => 'حقل :attribute غير صالح.',
    'array' => 'يجب أن يكون حقل :attribute مصفوفة.',
    'ascii' => 'يجب أن يحتوي حقل :attribute على حروف وأرقام ورموز أحادية البايت فقط.',
    'before' => 'يجب أن يكون حقل :attribute تاريخًا قبل :date.',
    'before_or_equal' => 'يجب أن يكون حقل :attribute تاريخًا قبل أو يساوي :date.',
    'between' => [
        'array' => 'يجب أن يحتوي حقل :attribute على عدد عناصر بين :min و :max.',
        'file' => 'يجب أن يكون حجم حقل :attribute بين :min و :max كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة حقل :attribute بين :min و :max.',
        'string' => 'يجب أن يكون عدد أحرف حقل :attribute بين :min و :max حرفًا.',
    ],
    'boolean' => 'يجب أن تكون قيمة حقل :attribute إما صحيحة أو خاطئة.',
    'can' => 'يحتوي حقل :attribute على قيمة غير مصرّح بها.',
    'confirmed' => 'تأكيد حقل :attribute غير متطابق.',
    'contains' => 'حقل :attribute تنقصه قيمة مطلوبة.',
    'current_password' => 'كلمة المرور غير صحيحة.',
    'date' => 'يجب أن يكون حقل :attribute تاريخًا صحيحًا.',
    'date_equals' => 'يجب أن يكون حقل :attribute تاريخًا يساوي :date.',
    'date_format' => 'يجب أن يطابق حقل :attribute الصيغة :format.',
    'decimal' => 'يجب أن يحتوي حقل :attribute على :decimal منزلة عشرية.',
    'declined' => 'يجب رفض حقل :attribute.',
    'declined_if' => 'يجب رفض حقل :attribute عندما يكون :other هو :value.',
    'different' => 'يجب أن يكون حقل :attribute وحقل :other مختلفين.',
    'digits' => 'يجب أن يتكوّن حقل :attribute من :digits أرقام.',
    'digits_between' => 'يجب أن يتكوّن حقل :attribute من عدد أرقام بين :min و :max.',
    'dimensions' => 'أبعاد صورة حقل :attribute غير صالحة.',
    'distinct' => 'حقل :attribute يحتوي على قيمة مكرّرة.',
    'doesnt_contain' => 'يجب ألا يحتوي حقل :attribute على أيٍّ مما يلي: :values.',
    'doesnt_end_with' => 'يجب ألا ينتهي حقل :attribute بأيٍّ مما يلي: :values.',
    'doesnt_start_with' => 'يجب ألا يبدأ حقل :attribute بأيٍّ مما يلي: :values.',
    'email' => 'يجب أن يكون حقل :attribute بريدًا إلكترونيًا صحيحًا.',
    'encoding' => 'يجب أن يكون ترميز حقل :attribute هو :encoding.',
    'ends_with' => 'يجب أن ينتهي حقل :attribute بأحد القيم التالية: :values.',
    'enum' => 'القيمة المحددة في حقل :attribute غير صالحة.',
    'exists' => 'القيمة المحددة في حقل :attribute غير صالحة.',
    'extensions' => 'يجب أن يكون امتداد حقل :attribute أحد الامتدادات التالية: :values.',
    'file' => 'يجب أن يكون حقل :attribute ملفًا.',
    'filled' => 'يجب أن تكون لحقل :attribute قيمة.',
    'gt' => [
        'array' => 'يجب أن يحتوي حقل :attribute على أكثر من :value عنصرًا.',
        'file' => 'يجب أن يكون حجم حقل :attribute أكبر من :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة حقل :attribute أكبر من :value.',
        'string' => 'يجب أن يكون عدد أحرف حقل :attribute أكبر من :value حرفًا.',
    ],
    'gte' => [
        'array' => 'يجب أن يحتوي حقل :attribute على :value عنصرًا أو أكثر.',
        'file' => 'يجب أن يكون حجم حقل :attribute أكبر من أو يساوي :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة حقل :attribute أكبر من أو تساوي :value.',
        'string' => 'يجب أن يكون عدد أحرف حقل :attribute أكبر من أو يساوي :value حرفًا.',
    ],
    'hex_color' => 'يجب أن يكون حقل :attribute لونًا سداسيًا عشريًا صحيحًا.',
    'image' => 'يجب أن يكون حقل :attribute صورة.',
    'in' => 'القيمة المحددة في حقل :attribute غير صالحة.',
    'in_array' => 'يجب أن يكون حقل :attribute موجودًا ضمن :other.',
    'in_array_keys' => 'يجب أن يحتوي حقل :attribute على مفتاح واحد على الأقل من المفاتيح التالية: :values.',
    'integer' => 'يجب أن يكون حقل :attribute عددًا صحيحًا.',
    'ip' => 'يجب أن يكون حقل :attribute عنوان IP صحيحًا.',
    'ipv4' => 'يجب أن يكون حقل :attribute عنوان IPv4 صحيحًا.',
    'ipv6' => 'يجب أن يكون حقل :attribute عنوان IPv6 صحيحًا.',
    'json' => 'يجب أن يكون حقل :attribute نص JSON صحيحًا.',
    'list' => 'يجب أن يكون حقل :attribute قائمة.',
    'lowercase' => 'يجب أن يكون حقل :attribute بأحرف صغيرة.',
    'lt' => [
        'array' => 'يجب أن يحتوي حقل :attribute على أقل من :value عنصرًا.',
        'file' => 'يجب أن يكون حجم حقل :attribute أقل من :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة حقل :attribute أقل من :value.',
        'string' => 'يجب أن يكون عدد أحرف حقل :attribute أقل من :value حرفًا.',
    ],
    'lte' => [
        'array' => 'يجب ألا يحتوي حقل :attribute على أكثر من :value عنصرًا.',
        'file' => 'يجب أن يكون حجم حقل :attribute أقل من أو يساوي :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة حقل :attribute أقل من أو تساوي :value.',
        'string' => 'يجب أن يكون عدد أحرف حقل :attribute أقل من أو يساوي :value حرفًا.',
    ],
    'mac_address' => 'يجب أن يكون حقل :attribute عنوان MAC صحيحًا.',
    'max' => [
        'array' => 'يجب ألا يحتوي حقل :attribute على أكثر من :max عنصرًا.',
        'file' => 'يجب ألا يتجاوز حجم حقل :attribute :max كيلوبايت.',
        'numeric' => 'يجب ألا تتجاوز قيمة حقل :attribute :max.',
        'string' => 'يجب ألا يتجاوز عدد أحرف حقل :attribute :max حرفًا.',
    ],
    'max_digits' => 'يجب ألا يحتوي حقل :attribute على أكثر من :max رقمًا.',
    'mimes' => 'يجب أن يكون حقل :attribute ملفًا من نوع: :values.',
    'mimetypes' => 'يجب أن يكون حقل :attribute ملفًا من نوع: :values.',
    'min' => [
        'array' => 'يجب أن يحتوي حقل :attribute على :min عنصرًا على الأقل.',
        'file' => 'يجب أن يكون حجم حقل :attribute :min كيلوبايت على الأقل.',
        'numeric' => 'يجب ألا تقل قيمة حقل :attribute عن :min.',
        'string' => 'يجب ألا يقل عدد أحرف حقل :attribute عن :min حرفًا.',
    ],
    'min_digits' => 'يجب أن يحتوي حقل :attribute على :min رقمًا على الأقل.',
    'missing' => 'يجب أن يكون حقل :attribute غير موجود.',
    'missing_if' => 'يجب أن يكون حقل :attribute غير موجود عندما يكون :other هو :value.',
    'missing_unless' => 'يجب أن يكون حقل :attribute غير موجود ما لم يكن :other هو :value.',
    'missing_with' => 'يجب أن يكون حقل :attribute غير موجود عند وجود :values.',
    'missing_with_all' => 'يجب أن يكون حقل :attribute غير موجود عند وجود :values.',
    'multiple_of' => 'يجب أن تكون قيمة حقل :attribute من مضاعفات :value.',
    'not_in' => 'القيمة المحددة في حقل :attribute غير صالحة.',
    'not_regex' => 'صيغة حقل :attribute غير صالحة.',
    'numeric' => 'يجب أن يكون حقل :attribute رقمًا.',
    'password' => [
        'letters' => 'يجب أن يحتوي حقل :attribute على حرف واحد على الأقل.',
        'mixed' => 'يجب أن يحتوي حقل :attribute على حرف كبير وحرف صغير على الأقل.',
        'numbers' => 'يجب أن يحتوي حقل :attribute على رقم واحد على الأقل.',
        'symbols' => 'يجب أن يحتوي حقل :attribute على رمز واحد على الأقل.',
        'uncompromised' => 'ظهرت قيمة حقل :attribute في تسريب بيانات. يرجى اختيار قيمة مختلفة.',
    ],
    'present' => 'يجب أن يكون حقل :attribute موجودًا.',
    'present_if' => 'يجب أن يكون حقل :attribute موجودًا عندما يكون :other هو :value.',
    'present_unless' => 'يجب أن يكون حقل :attribute موجودًا ما لم يكن :other هو :value.',
    'present_with' => 'يجب أن يكون حقل :attribute موجودًا عند وجود :values.',
    'present_with_all' => 'يجب أن يكون حقل :attribute موجودًا عند وجود :values.',
    'prohibited' => 'حقل :attribute ممنوع.',
    'prohibited_if' => 'حقل :attribute ممنوع عندما يكون :other هو :value.',
    'prohibited_if_accepted' => 'حقل :attribute ممنوع عند قبول :other.',
    'prohibited_if_declined' => 'حقل :attribute ممنوع عند رفض :other.',
    'prohibited_unless' => 'حقل :attribute ممنوع ما لم يكن :other ضمن :values.',
    'prohibits' => 'حقل :attribute يمنع وجود :other.',
    'regex' => 'صيغة حقل :attribute غير صالحة.',
    'required' => 'حقل :attribute مطلوب.',
    'required_array_keys' => 'يجب أن يحتوي حقل :attribute على مدخلات لـ: :values.',
    'required_if' => 'حقل :attribute مطلوب عندما يكون :other هو :value.',
    'required_if_accepted' => 'حقل :attribute مطلوب عند قبول :other.',
    'required_if_declined' => 'حقل :attribute مطلوب عند رفض :other.',
    'required_unless' => 'حقل :attribute مطلوب ما لم يكن :other ضمن :values.',
    'required_with' => 'حقل :attribute مطلوب عند وجود :values.',
    'required_with_all' => 'حقل :attribute مطلوب عند وجود :values.',
    'required_without' => 'حقل :attribute مطلوب عند عدم وجود :values.',
    'required_without_all' => 'حقل :attribute مطلوب عند عدم وجود أيٍّ من :values.',
    'same' => 'يجب أن يتطابق حقل :attribute مع :other.',
    'size' => [
        'array' => 'يجب أن يحتوي حقل :attribute على :size عنصرًا.',
        'file' => 'يجب أن يكون حجم حقل :attribute :size كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة حقل :attribute :size.',
        'string' => 'يجب أن يكون عدد أحرف حقل :attribute :size حرفًا.',
    ],
    'starts_with' => 'يجب أن يبدأ حقل :attribute بأحد القيم التالية: :values.',
    'string' => 'يجب أن يكون حقل :attribute نصًا.',
    'timezone' => 'يجب أن يكون حقل :attribute منطقة زمنية صحيحة.',
    'unique' => 'قيمة حقل :attribute مستخدمة مسبقًا.',
    'uploaded' => 'فشل رفع حقل :attribute.',
    'uppercase' => 'يجب أن يكون حقل :attribute بأحرف كبيرة.',
    'url' => 'يجب أن يكون حقل :attribute رابطًا صحيحًا.',
    'ulid' => 'يجب أن يكون حقل :attribute معرّف ULID صحيحًا.',
    'uuid' => 'يجب أن يكون حقل :attribute معرّف UUID صحيحًا.',

    /*
    |--------------------------------------------------------------------------
    | أسطر لغة التحقق المخصصة
    |--------------------------------------------------------------------------
    |
    | يمكنك هنا تحديد رسائل تحقق مخصصة للحقول باستخدام الصيغة "الحقل.القاعدة"
    | لتسمية الأسطر. هذا يسهّل تحديد سطر لغة مخصص لقاعدة حقل معيّن.
    |
    */

    'custom' => [
        'photos' => [
            'required' => 'يجب رفع صورة واحدة على الأقل.',
        ],
        'photos.*' => [
            'max' => 'يجب ألا يتجاوز حجم كل صورة 5 ميجابايت.',
            'mimes' => 'يجب أن تكون الصور بصيغة JPG أو PNG أو WebP.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | أسماء الحقول المخصصة
    |--------------------------------------------------------------------------
    |
    | تُستخدم الأسطر التالية لاستبدال العنصر النائب للحقل بشيء أكثر وضوحًا
    | للقارئ، مثل "البريد الإلكتروني" بدلًا من "email". هذا يجعل رسائلنا
    | أكثر تعبيرًا.
    |
    */

    'attributes' => [
        'name' => 'الاسم',
        'email' => 'البريد الإلكتروني',
        'password' => 'كلمة المرور',
        'password_confirmation' => 'تأكيد كلمة المرور',
        'current_password' => 'كلمة المرور الحالية',
        'new_password' => 'كلمة المرور الجديدة',
        'new_password_confirmation' => 'تأكيد كلمة المرور الجديدة',
        'phone' => 'رقم الهاتف',
        'role' => 'الدور',
        'specialty' => 'التخصص',
        'bio' => 'النبذة التعريفية',
        'avatar' => 'الصورة الشخصية',
        'token' => 'الرمز',

        'workspace_name' => 'اسم مساحة العمل',
        'description' => 'الوصف',
        'city' => 'المدينة',
        'area' => 'المنطقة',
        'address' => 'العنوان',
        'lat' => 'خط العرض',
        'lng' => 'خط الطول',
        'capacity' => 'السعة',
        'hours' => 'ساعات العمل',
        'amenities' => 'المرافق',
        'amenities.*' => 'المرفق',
        'license_file' => 'ملف السجل التجاري',
        'id_document' => 'وثيقة الهوية',
        'photos' => 'الصور',
        'photos.*' => 'الصورة',

        'seat_types' => 'أنواع المقاعد',
        'seat_types.*.type' => 'نوع المقعد',
        'seat_types.*.price_monthly' => 'السعر الشهري',
        'seat_types.*.price_daily' => 'السعر اليومي',
        'seat_types.*.capacity' => 'السعة',
        'seat_types.*.enabled' => 'مُفعّل',

        'type' => 'النوع',
        'seat_number' => 'رقم المقعد',
        'price_monthly' => 'السعر الشهري',
        'price_daily' => 'السعر اليومي',
        'status' => 'الحالة',
        'seat_id' => 'المقعد',
        'member_id' => 'العضو',
        'workspace_id' => 'مساحة العمل',
        'package_id' => 'الباقة',
        'subscription_id' => 'الاشتراك',
        'invoice_id' => 'الفاتورة',
        'message' => 'الرسالة',
        'title' => 'العنوان',
        'body' => 'المحتوى',
        'rating' => 'التقييم',
        'comment' => 'التعليق',
        'amount' => 'المبلغ',
        'due_date' => 'تاريخ الاستحقاق',
        'paid_at' => 'تاريخ الدفع',
        'notes' => 'الملاحظات',
        'receipt' => 'الإيصال',
        'reason' => 'السبب',
        'duration_months' => 'المدة (بالأشهر)',
        'starts_at' => 'تاريخ البدء',

        'channel' => 'القناة',
        'recipient' => 'المستلم',
        'content' => 'المحتوى',
        'key' => 'المفتاح',
        'value' => 'القيمة',
        'enabled' => 'مُفعّل',

        'category' => 'الفئة',
        'spent_on' => 'تاريخ الصرف',
        'quantity' => 'الكمية',
    ],

];
