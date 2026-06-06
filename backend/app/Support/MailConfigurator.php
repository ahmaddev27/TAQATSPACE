<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Mail;

/**
 * Rebinds Laravel's `smtp` mailer at runtime from a decrypted SMTP config so a
 * Mailable can be delivered through an account chosen per-request (the platform
 * account or a workspace's own). When the supplied config is empty/incomplete
 * the app's default mailer is left untouched (callers fall back to it).
 */
final class MailConfigurator
{
    /**
     * Apply the given SMTP config to the `smtp` mailer + global "from", and
     * return the mailer name to send through.
     *
     * @param  array{host?: ?string, port?: int|string|null, username?: ?string, password?: ?string, encryption?: ?string, from_address?: ?string, from_name?: ?string}  $smtp
     * @return string the mailer name to pass to {@see Mail::mailer()} —
     *                'smtp' when reconfigured, otherwise the app default
     */
    public static function apply(array $smtp): string
    {
        $host = (string) ($smtp['host'] ?? '');

        if ($host === '') {
            // Nothing usable supplied — caller should use the default mailer.
            return (string) config('mail.default', 'smtp');
        }

        $fromAddress = (string) ($smtp['from_address'] ?? config('mail.from.address'));
        $fromName = (string) ($smtp['from_name'] ?? config('mail.from.name'));

        config([
            'mail.mailers.smtp' => [
                'transport' => 'smtp',
                'host' => $host,
                'port' => (int) ($smtp['port'] ?? 587),
                'username' => $smtp['username'] ?? null,
                'password' => $smtp['password'] ?? null,
                'encryption' => self::normalizeEncryption($smtp['encryption'] ?? null),
                'timeout' => null,
                'local_domain' => parse_url((string) config('app.url'), PHP_URL_HOST) ?: null,
            ],
            'mail.from.address' => $fromAddress,
            'mail.from.name' => $fromName,
        ]);

        // Forget any already-resolved mailer so the new config takes effect.
        Mail::forgetMailers();

        return 'smtp';
    }

    /**
     * Coerce the stored encryption value into what the SMTP transport expects
     * ('tls' / 'ssl' / null). Anything blank or "none" disables encryption.
     */
    private static function normalizeEncryption(mixed $encryption): ?string
    {
        $value = is_string($encryption) ? strtolower(trim($encryption)) : '';

        return in_array($value, ['tls', 'ssl'], true) ? $value : null;
    }
}
