<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Derives a human-readable push **title** and **body** from a stored database
 * notification payload (its `toArray()` output), so any notification can be
 * mirrored to FCM without each class defining push copy.
 *
 * Strategy:
 *   - If the payload already carries `title`/`body` (e.g. announcements), use
 *     them verbatim.
 *   - Otherwise map the payload `type` to a localized title, and build a body
 *     from the most relevant contextual field (workspace name, invoice number…).
 *
 * Titles/bodies are resolved through the translator using the request locale,
 * matching the rest of the API's localized strings.
 */
final class NotificationPushContent
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: string, 1: string}  [title, body]
     */
    public static function resolve(array $payload): array
    {
        if (self::isNonEmptyString($payload['title'] ?? null)) {
            return [
                self::truncate((string) $payload['title'], 120),
                self::isNonEmptyString($payload['body'] ?? null)
                    ? self::truncate((string) $payload['body'], 240)
                    : '',
            ];
        }

        $type = is_string($payload['type'] ?? null) ? $payload['type'] : 'default';

        return [
            self::title($type),
            self::body($type, $payload),
        ];
    }

    private static function title(string $type): string
    {
        $key = "notifications.push.{$type}.title";
        $translated = __($key);

        // __() returns the key itself when missing — fall back to a generic title.
        return is_string($translated) && $translated !== $key
            ? $translated
            : (string) __('notifications.push.default.title');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function body(string $type, array $payload): string
    {
        $body = match ($type) {
            'new_message' => self::stringField($payload, 'preview'),
            'invoice_created', 'invoice_paid', 'invoice_overdue', 'invoice_reminder' => self::invoiceBody($type, $payload),
            'seat_assigned' => self::translatedBody('seat_assigned', [
                'seat' => self::stringField($payload, 'seat_number'),
                'workspace' => self::stringField($payload, 'workspace_name'),
            ]),
            'booking_approved', 'booking_rejected' => self::translatedBody($type, [
                'workspace' => self::stringField($payload, 'workspace_name'),
            ]),
            'new_booking_request' => self::translatedBody('new_booking_request', [
                'name' => self::stringField($payload, 'member_name'),
                'workspace' => self::stringField($payload, 'workspace_name'),
            ]),
            'new_workspace_registration' => self::translatedBody('new_workspace_registration', [
                'name' => self::stringField($payload, 'owner_name'),
            ]),
            default => self::stringField($payload, 'workspace_name'),
        };

        return self::truncate($body, 240);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function invoiceBody(string $type, array $payload): string
    {
        return self::translatedBody($type, [
            'number' => self::stringField($payload, 'invoice_number'),
            'amount' => self::stringField($payload, 'amount'),
        ]);
    }

    /**
     * @param  array<string, string>  $replace
     */
    private static function translatedBody(string $type, array $replace): string
    {
        $key = "notifications.push.{$type}.body";
        $translated = __($key, $replace);

        return is_string($translated) && $translated !== $key ? $translated : '';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function stringField(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;

        return is_scalar($value) ? (string) $value : '';
    }

    private static function isNonEmptyString(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    private static function truncate(string $value, int $limit): string
    {
        $value = trim($value);

        return mb_strlen($value) > $limit
            ? mb_substr($value, 0, $limit).'…'
            : $value;
    }
}
