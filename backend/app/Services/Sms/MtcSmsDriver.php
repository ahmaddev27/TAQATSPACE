<?php

declare(strict_types=1);

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * SMS driver for mtcsms.com — a simple HTTP SMS gateway keyed by
 * username / password / sender / numbers / message.
 *
 * The config is supplied per-send (resolved from the platform or a workspace),
 * never read from global app config, so the same driver instance can serve
 * multiple accounts.
 */
final class MtcSmsDriver implements SmsDriver
{
    private const BASE_URL = 'https://www.mtcsms.com';

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
            // TODO: confirm exact endpoint/params with provider docs (mtcsms.com)
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->asForm()
                ->post(self::BASE_URL.'/api/sendsms', [
                    'username' => (string) ($this->config['username'] ?? ''),
                    'password' => (string) ($this->config['password'] ?? $this->config['api_key'] ?? ''),
                    'sender' => (string) ($this->config['sender'] ?? ''),
                    'numbers' => $to,
                    'message' => $message,
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
