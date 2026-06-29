<?php

declare(strict_types=1);

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * SMS driver for HotSMS (hotsms.ps). The gateway is a single GET endpoint
 * (sendbulksms.php) authenticated either by an api_token or by user_name +
 * user_pass, with sender / mobile / type / text, all URL-encoded.
 *
 * The config is supplied per-send (resolved from the platform or a workspace),
 * never read from global app config. Maps the stored messaging-settings fields:
 * `api_key` -> `api_token` (preferred), else `username`/`password` ->
 * `user_name`/`user_pass`; `sender` -> `sender`.
 */
final class HotSmsDriver implements SmsDriver
{
    private const BASE_URL = 'http://hotsms.ps/sendbulksms.php';

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
            $params = [
                'sender' => (string) ($this->config['sender'] ?? ''),
                'mobile' => $to,
                'type' => 0,
                'text' => $message,
            ];

            // Prefer the API token; fall back to username/password credentials.
            if (! empty($this->config['api_key'])) {
                $params['api_token'] = (string) $this->config['api_key'];
            } else {
                $params['user_name'] = (string) ($this->config['username'] ?? '');
                $params['user_pass'] = (string) ($this->config['password'] ?? '');
            }

            $response = Http::timeout(self::TIMEOUT_SECONDS)->get(self::BASE_URL, $params);

            return [
                'ok' => $response->successful(),
                'response' => $response->json() ?? $response->body(),
            ];
        } catch (Throwable $e) {
            Log::warning('HotSms send failed', ['error' => $e->getMessage()]);

            return ['ok' => false, 'response' => $e->getMessage()];
        }
    }
}
