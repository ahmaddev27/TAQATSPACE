<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\TestMessageMail;
use App\Models\SiteSetting;
use App\Models\Workspace;
use App\Services\Sms\SmsService;
use App\Support\MailConfigurator;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Single source of truth for messaging configuration (SMTP email + SMS) at two
 * levels:
 *
 *  - Platform: Taqat's own accounts, stored in the `messaging` site_setting row.
 *  - Workspace: an owner's own accounts, stored in the workspace's `messaging`
 *    JSON column (or `use_platform = true` to inherit the platform accounts).
 *
 * Secrets (SMTP password, SMS credentials) are encrypted at rest with
 * {@see Crypt::encryptString} and are NEVER returned in masked output — callers
 * get `has_*` booleans instead. The decrypted values are only ever exposed
 * through the `*Raw` / `forWorkspace` resolution helpers used by send paths.
 */
class MessagingSettingsService
{
    private const SETTING_KEY = 'messaging';

    /** SMTP secret sub-keys that must be encrypted at rest. */
    private const SMTP_SECRETS = ['password'];

    /** SMS secret sub-keys that must be encrypted at rest. */
    private const SMS_SECRETS = ['username', 'password', 'api_key'];

    public function __construct(
        private readonly SmsService $sms,
    ) {}

    // ---- Platform (Super Admin) ---------------------------------------------

    /**
     * Masked platform config for API responses: non-secret fields plus
     * `has_password` / `has_credentials` flags. Never contains a plaintext
     * secret.
     *
     * @return array{smtp: array<string, mixed>, sms: array<string, mixed>}
     */
    public function getPlatform(): array
    {
        $raw = $this->rawPlatform();

        return [
            'smtp' => $this->maskSmtp($raw['smtp']),
            'sms' => $this->maskSms($raw['sms']),
        ];
    }

    /**
     * Decrypted platform config for the actual send paths. Contains plaintext
     * secrets — never return this from a controller.
     *
     * @return array{smtp: array<string, mixed>, sms: array<string, mixed>}
     */
    public function getPlatformRaw(): array
    {
        $raw = $this->rawPlatform();

        return [
            'smtp' => $this->decryptSecrets($raw['smtp'], self::SMTP_SECRETS),
            'sms' => $this->decryptSecrets($raw['sms'], self::SMS_SECRETS),
        ];
    }

    /**
     * Persist the platform config. Secrets are encrypted; a blank/absent secret
     * preserves the currently stored one (so masked round-trips don't wipe it).
     *
     * @param  array{smtp?: array<string, mixed>, sms?: array<string, mixed>}  $input
     * @return array{smtp: array<string, mixed>, sms: array<string, mixed>} the masked result
     */
    public function updatePlatform(array $input): array
    {
        $current = $this->rawPlatform();

        $merged = [
            'smtp' => $this->mergeSmtp($current['smtp'], $input['smtp'] ?? []),
            'sms' => $this->mergeSms($current['sms'], $input['sms'] ?? []),
        ];

        SiteSetting::updateOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => $merged],
        );

        return $this->getPlatform();
    }

    // ---- Workspace (Owner) --------------------------------------------------

    /**
     * Persist a workspace's own messaging config. Mirrors {@see updatePlatform}
     * with the addition of the `use_platform` toggle.
     *
     * @param  array{use_platform?: bool, smtp?: array<string, mixed>, sms?: array<string, mixed>}  $input
     * @return array<string, mixed> the masked workspace messaging block
     */
    public function updateWorkspace(Workspace $workspace, array $input): array
    {
        $current = $this->rawWorkspace($workspace);

        $merged = [
            'use_platform' => array_key_exists('use_platform', $input)
                ? (bool) $input['use_platform']
                : ($current['use_platform'] ?? true),
            'smtp' => $this->mergeSmtp($current['smtp'], $input['smtp'] ?? []),
            'sms' => $this->mergeSms($current['sms'], $input['sms'] ?? []),
        ];

        $workspace->update(['messaging' => $merged]);

        return $this->maskWorkspace($merged);
    }

    /**
     * Masked workspace messaging block for {@see \App\Http\Resources\WorkspaceResource}.
     *
     * @return array<string, mixed>
     */
    public function maskedForWorkspace(Workspace $workspace): array
    {
        return $this->maskWorkspace($this->rawWorkspace($workspace));
    }

    /**
     * Resolve the DECRYPTED config a workspace should actually send through:
     * the workspace's own config when `use_platform = false` and it is
     * configured for that channel, otherwise the platform config.
     *
     * @return array{smtp: array<string, mixed>, sms: array<string, mixed>}
     */
    public function forWorkspace(Workspace $workspace): array
    {
        $platform = $this->getPlatformRaw();

        $raw = $this->rawWorkspace($workspace);

        if (($raw['use_platform'] ?? true) === true) {
            return $platform;
        }

        $smtp = $this->decryptSecrets($raw['smtp'], self::SMTP_SECRETS);
        $sms = $this->decryptSecrets($raw['sms'], self::SMS_SECRETS);

        return [
            'smtp' => $this->isSmtpConfigured($smtp) ? $smtp : $platform['smtp'],
            'sms' => $this->isSmsConfigured($sms) ? $sms : $platform['sms'],
        ];
    }

    // ---- Test sends (platform config) ---------------------------------------

    /**
     * Send a test email or SMS using the decrypted PLATFORM config. Never
     * throws — driver/transport errors are caught and reported so the endpoint
     * can return a friendly message rather than a 500.
     *
     * @return array{ok: bool, message: string}
     */
    public function sendPlatformTest(string $channel, string $to): array
    {
        $raw = $this->getPlatformRaw();

        return $channel === 'email'
            ? $this->sendTestEmail($raw['smtp'], $to)
            : $this->sendTestSms($raw['sms'], $to);
    }

    /**
     * @param  array<string, mixed>  $smtp  decrypted SMTP config
     * @return array{ok: bool, message: string}
     */
    private function sendTestEmail(array $smtp, string $to): array
    {
        try {
            $mailer = MailConfigurator::apply($smtp);

            Mail::mailer($mailer)->to($to)->send(new TestMessageMail());

            return ['ok' => true, 'message' => "Test email sent to {$to}."];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Test email failed: '.$e->getMessage()];
        }
    }

    /**
     * @param  array<string, mixed>  $sms  decrypted SMS config
     * @return array{ok: bool, message: string}
     */
    private function sendTestSms(array $sms, string $to): array
    {
        if (! $this->isSmsConfigured($sms)) {
            return ['ok' => false, 'message' => 'SMS is not configured for the platform.'];
        }

        $result = $this->sms->send(
            $to,
            'Taqat test message: your SMS configuration works.',
            $sms,
        );

        return [
            'ok' => $result['ok'],
            'message' => $result['ok']
                ? "Test SMS sent to {$to}."
                : 'Test SMS failed: '.$this->stringifyResponse($result['response']),
        ];
    }

    private function stringifyResponse(mixed $response): string
    {
        if (is_string($response)) {
            return $response;
        }

        return (string) json_encode($response);
    }

    // ---- Raw readers --------------------------------------------------------

    /**
     * @return array{smtp: array<string, mixed>, sms: array<string, mixed>}
     */
    private function rawPlatform(): array
    {
        $setting = SiteSetting::query()->where('key', self::SETTING_KEY)->first();

        /** @var array<string, mixed> $value */
        $value = $setting?->value ?? [];

        return [
            'smtp' => is_array($value['smtp'] ?? null) ? $value['smtp'] : [],
            'sms' => is_array($value['sms'] ?? null) ? $value['sms'] : [],
        ];
    }

    /**
     * @return array{use_platform: bool, smtp: array<string, mixed>, sms: array<string, mixed>}
     */
    private function rawWorkspace(Workspace $workspace): array
    {
        /** @var array<string, mixed> $value */
        $value = $workspace->messaging ?? [];

        return [
            'use_platform' => array_key_exists('use_platform', $value)
                ? (bool) $value['use_platform']
                : true,
            'smtp' => is_array($value['smtp'] ?? null) ? $value['smtp'] : [],
            'sms' => is_array($value['sms'] ?? null) ? $value['sms'] : [],
        ];
    }

    // ---- Merge (encrypt + preserve-blank) -----------------------------------

    /**
     * @param  array<string, mixed>  $current  stored (encrypted secrets)
     * @param  array<string, mixed>  $input    submitted (plaintext secrets)
     * @return array<string, mixed>
     */
    private function mergeSmtp(array $current, array $input): array
    {
        $merged = $current;

        foreach (['host', 'port', 'encryption', 'from_address', 'from_name'] as $field) {
            if (array_key_exists($field, $input)) {
                $merged[$field] = $field === 'port'
                    ? ($input[$field] === null || $input[$field] === '' ? null : (int) $input[$field])
                    : $input[$field];
            }
        }

        // `username` is not a secret for SMTP (it is for SMS) — store as-is.
        if (array_key_exists('username', $input)) {
            $merged['username'] = $input['username'];
        }

        return $this->applySecrets($merged, $input, self::SMTP_SECRETS);
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function mergeSms(array $current, array $input): array
    {
        $merged = $current;

        foreach (['provider', 'sender'] as $field) {
            if (array_key_exists($field, $input)) {
                $merged[$field] = $input[$field];
            }
        }

        return $this->applySecrets($merged, $input, self::SMS_SECRETS);
    }

    /**
     * Encrypt each submitted non-empty secret; a blank/absent secret keeps the
     * existing stored (encrypted) value.
     *
     * @param  array<string, mixed>  $merged
     * @param  array<string, mixed>  $input
     * @param  list<string>  $secretKeys
     * @return array<string, mixed>
     */
    private function applySecrets(array $merged, array $input, array $secretKeys): array
    {
        foreach ($secretKeys as $key) {
            if (! array_key_exists($key, $input)) {
                continue;
            }

            $value = $input[$key];

            if ($value === null || $value === '') {
                // Blank submitted: preserve whatever is already stored.
                continue;
            }

            $merged[$key] = Crypt::encryptString((string) $value);
        }

        return $merged;
    }

    // ---- Decrypt ------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $config
     * @param  list<string>  $secretKeys
     * @return array<string, mixed>
     */
    private function decryptSecrets(array $config, array $secretKeys): array
    {
        foreach ($secretKeys as $key) {
            if (! isset($config[$key]) || ! is_string($config[$key]) || $config[$key] === '') {
                continue;
            }

            try {
                $config[$key] = Crypt::decryptString($config[$key]);
            } catch (DecryptException) {
                // Value was never encrypted (or key rotated) — drop it rather
                // than leak ciphertext into a send path.
                unset($config[$key]);
            }
        }

        return $config;
    }

    // ---- Masking ------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $smtp
     * @return array<string, mixed>
     */
    private function maskSmtp(array $smtp): array
    {
        return [
            'host' => $smtp['host'] ?? null,
            'port' => isset($smtp['port']) ? (int) $smtp['port'] : null,
            'username' => $smtp['username'] ?? null,
            'encryption' => $smtp['encryption'] ?? null,
            'from_address' => $smtp['from_address'] ?? null,
            'from_name' => $smtp['from_name'] ?? null,
            'has_password' => $this->hasSecret($smtp, 'password'),
        ];
    }

    /**
     * @param  array<string, mixed>  $sms
     * @return array<string, mixed>
     */
    private function maskSms(array $sms): array
    {
        return [
            'provider' => $sms['provider'] ?? null,
            'sender' => $sms['sender'] ?? null,
            'has_credentials' => $this->hasSecret($sms, 'username')
                || $this->hasSecret($sms, 'password')
                || $this->hasSecret($sms, 'api_key'),
        ];
    }

    /**
     * @param  array{use_platform?: bool, smtp?: array<string, mixed>, sms?: array<string, mixed>}  $raw
     * @return array<string, mixed>
     */
    private function maskWorkspace(array $raw): array
    {
        return [
            'use_platform' => $raw['use_platform'] ?? true,
            'smtp' => $this->maskSmtp($raw['smtp'] ?? []),
            'sms' => $this->maskSms($raw['sms'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function hasSecret(array $config, string $key): bool
    {
        return isset($config[$key]) && is_string($config[$key]) && $config[$key] !== '';
    }

    // ---- "Is configured" checks (decrypted shape) ---------------------------

    /**
     * @param  array<string, mixed>  $smtp
     */
    private function isSmtpConfigured(array $smtp): bool
    {
        return ! empty($smtp['host']);
    }

    /**
     * @param  array<string, mixed>  $sms
     */
    private function isSmsConfigured(array $sms): bool
    {
        return ! empty($sms['provider'])
            && (! empty($sms['username']) || ! empty($sms['password']) || ! empty($sms['api_key']));
    }
}
