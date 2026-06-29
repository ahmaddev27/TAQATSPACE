<?php

declare(strict_types=1);

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * SMS driver for MTC (int.mtcsms.com). The gateway is a single GET endpoint that
 * takes username / password / from (sender) / to / msg / type, URL-encoded.
 *
 * The config is supplied per-send (resolved from the platform or a workspace),
 * never read from global app config, so one driver instance can serve multiple
 * accounts. Maps the stored messaging-settings field `sender` -> `from`.
 */
final class MtcSmsDriver implements SmsDriver
{
    private const BASE_URL = 'https://int.mtcsms.com/sendsms.aspx';

    private const TIMEOUT_SECONDS = 15;

    /**
     * @param  array{sender?: ?string, username?: ?string, password?: ?string, api_key?: ?string}  $config
     */
    public function __construct(
        private readonly array $config,
    ) {}

    public function send(string $to, string $message): array
    {
        try {
            // GET with query params (Http url-encodes them). `type=0` is the
            // gateway's default message type.
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->get(self::BASE_URL, [
                    'username' => (string) ($this->config['username'] ?? ''),
                    'password' => (string) ($this->config['password'] ?? $this->config['api_key'] ?? ''),
                    'from' => (string) ($this->config['sender'] ?? ''),
                    'to' => $to,
                    'msg' => $message,
                    'type' => 0,
                ]);

            return [
                'ok' => $response->successful(),
                'response' => $response->json() ?? $response->body(),
            ];
        } catch (Throwable $e) {
            Log::warning('MtcSms send failed', ['error' => $e->getMessage()]);

            return ['ok' => false, 'response' => $e->getMessage()];
        }
    }
}
