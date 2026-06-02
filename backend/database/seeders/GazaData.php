<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Support\Arr;

/**
 * Linguistically correct, Gaza-context seed data. Used by factories and the
 * DatabaseSeeder so the development dataset reads like the real product
 * instead of Faker's Western defaults.
 */
final class GazaData
{
    /** @var list<string> */
    public const MALE_NAMES = [
        'أحمد', 'محمد', 'محمود', 'يوسف', 'عمر', 'خالد', 'إبراهيم', 'سامي',
        'كريم', 'باسل', 'رامي', 'مصطفى', 'عبد الله', 'حسن', 'وسيم', 'أنس',
    ];

    /** @var list<string> */
    public const FEMALE_NAMES = [
        'نور', 'سارة', 'ليان', 'هبة', 'رنا', 'دعاء', 'مريم', 'آية',
        'لمى', 'سلمى', 'تالا', 'ميس', 'رهف', 'بيان', 'جنى', 'ملك',
    ];

    /** @var list<string> */
    public const FAMILY_NAMES = [
        'الفرا', 'حمدان', 'أبو رمضان', 'الأغا', 'شاهين', 'النجار', 'مقداد',
        'الشوا', 'أبو شعبان', 'الريّس', 'صيدم', 'البطّة', 'مطر', 'الكحلوت', 'زقوت',
    ];

    /** @var list<array{city:string, area:string}> */
    public const LOCATIONS = [
        ['city' => 'غزة', 'area' => 'الرمال'],
        ['city' => 'غزة', 'area' => 'الشيخ رضوان'],
        ['city' => 'جباليا', 'area' => 'جباليا البلد'],
        ['city' => 'بيت لاهيا', 'area' => 'العطاطرة'],
        ['city' => 'خان يونس', 'area' => 'المدينة'],
        ['city' => 'دير البلح', 'area' => 'البلد'],
        ['city' => 'رفح', 'area' => 'تل السلطان'],
    ];

    /** @var list<string> */
    public const SPECIALTIES = [
        'مطوّر واجهات أمامية',
        'مطوّر تطبيقات',
        'مصمم جرافيك',
        'كاتب محتوى',
        'أخصائي تسويق رقمي',
        'محاسب',
        'محلل بيانات',
        'مترجم',
        'مصمم UI/UX',
        'مدير مشاريع',
    ];

    /** @var list<string> */
    public const WORKSPACE_NAMES = [
        'مساحة المنارة',
        'مساحة إبداع',
        'مساحة رُوّاد',
        'مركز طاقة',
        'مساحة الملتقى',
        'مساحة أفق',
        'مساحة كود',
        'مساحة الواحة',
    ];

    /** @var list<string> */
    public const AMENITIES = [
        'wifi', 'printer', 'meeting_room', 'parking', 'coffee', 'kitchen', 'snow',
    ];

    public static function personName(?string $gender = null): string
    {
        $gender ??= Arr::random(['male', 'female']);
        $first = $gender === 'female'
            ? Arr::random(self::FEMALE_NAMES)
            : Arr::random(self::MALE_NAMES);

        return $first.' '.Arr::random(self::FAMILY_NAMES);
    }

    public static function specialty(): string
    {
        return Arr::random(self::SPECIALTIES);
    }

    public static function location(): array
    {
        return Arr::random(self::LOCATIONS);
    }

    public static function phone(): string
    {
        return '059'.str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
    }

    /**
     * A subset of amenity codes for a workspace.
     *
     * @return list<string>
     */
    public static function amenities(): array
    {
        $count = random_int(3, count(self::AMENITIES));

        return array_values(Arr::random(self::AMENITIES, $count));
    }

    /**
     * Gaza Strip coordinates with small jitter, kept inside realistic bounds.
     *
     * @return array{lat:float, lng:float}
     */
    public static function coordinates(): array
    {
        return [
            'lat' => round(31.40 + (random_int(0, 2000) / 10000), 7),  // ~31.40–31.60
            'lng' => round(34.30 + (random_int(0, 2000) / 10000), 7),  // ~34.30–34.50
        ];
    }

    /**
     * Default weekly working hours (Sat–Thu open, Fri closed).
     *
     * @return array<string, string|array{open:string, close:string}>
     */
    public static function workingHours(): array
    {
        $open = ['open' => '09:00', 'close' => '18:00'];

        return [
            'sat' => $open,
            'sun' => $open,
            'mon' => $open,
            'tue' => $open,
            'wed' => $open,
            'thu' => $open,
            'fri' => 'closed',
        ];
    }
}
