<?php

declare(strict_types=1);

namespace App\Services\Sms;

/**
 * Entry point for sending SMS. Resolves the concrete {@see SmsDriver} from the
 * supplied (already-decrypted) config's `provider` key and delegates the send.
 *
 * The config is passed in per call rather than injected so the same service can
 * send via the platform account or any workspace's own account.
 */
class SmsService
{
    /**
     * The provider identifiers this service can route to.
     *
     * @var array<string, class-string<SmsDriver>>
     */
    private const DRIVERS = [
        'hotsms' => HotSmsDriver::class,
        'mtcsms' => MtcSmsDriver::class,
    ];

    /**
     * Send a message using the account described by $config.
     *
     * @param  array{provider?: ?string, sender?: ?string, username?: ?string, password?: ?string, api_key?: ?string}  $config
     * @return array{ok: bool, response: mixed}
     */
    public function send(string $to, string $message, array $config): array
    {
        $provider = (string) ($config['provider'] ?? '');

        $driverClass = self::DRIVERS[$provider] ?? null;

        if ($driverClass === null) {
            return [
                'ok' => false,
                'response' => "Unsupported or unconfigured SMS provider [{$provider}].",
            ];
        }

        return (new $driverClass($config))->send($to, $message);
    }

    /**
     * The supported provider identifiers (for validation / docs).
     *
     * @return list<string>
     */
    public static function providers(): array
    {
        return array_keys(self::DRIVERS);
    }
}
