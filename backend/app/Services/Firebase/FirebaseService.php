<?php

declare(strict_types=1);

namespace App\Services\Firebase;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Thin Firebase integration for the realtime milestone:
 *
 *  1. Firebase Cloud Messaging (FCM HTTP v1) push delivery, and
 *  2. minting Firebase **custom tokens** so the SPA can authenticate against
 *     Firestore for chat.
 *
 * Design goals:
 *
 *  - **Fully env-gated & graceful.** When no service account is configured the
 *    service reports {@see isConfigured()} === false and every operation is a
 *    safe no-op that never throws. The rest of the app is unaffected.
 *  - **No heavy SDK.** Tokens are RS256-signed locally with the service-account
 *    private key (via firebase/php-jwt when present, otherwise openssl_sign),
 *    and FCM is called over plain HTTP. The only network call is to Google's
 *    OAuth2 token endpoint (for an FCM access token) and to FCM itself.
 */
class FirebaseService
{
    /** Audience required by the Identity Toolkit for Firebase custom tokens. */
    private const CUSTOM_TOKEN_AUDIENCE =
        'https://identitytoolkit.googleapis.com/google.identity.identitytoolkit.v1.IdentityToolkit';

    /** OAuth2 scope needed to call FCM HTTP v1. */
    private const FCM_SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private const GOOGLE_TOKEN_URI = 'https://oauth2.googleapis.com/token';

    /** Cache key + TTL for the short-lived FCM OAuth2 access token. */
    private const ACCESS_TOKEN_CACHE_KEY = 'firebase:fcm:access_token';

    private const ACCESS_TOKEN_TTL_SECONDS = 3300; // ~55 min (Google issues 1h)

    /**
     * The resolved service-account credentials, or null when unconfigured.
     *
     * @var array{project_id: string, client_email: string, private_key: string}|null
     */
    private ?array $credentials;

    public function __construct()
    {
        $this->credentials = $this->resolveCredentials();
    }

    /**
     * True only when a complete service account (project id, client email and
     * private key) is available. Callers should branch on this; every method
     * below is independently safe when false.
     */
    public function isConfigured(): bool
    {
        return $this->credentials !== null;
    }

    /**
     * Mint a Firebase **custom token** for the given uid so the client can call
     * `signInWithCustomToken()` and authenticate against Firestore.
     *
     * Returns null (never throws) when Firebase is not configured.
     *
     * @param  array<string, mixed>  $claims  Additional, non-reserved custom claims.
     */
    public function mintCustomToken(string $uid, array $claims = []): ?string
    {
        if ($this->credentials === null) {
            return null;
        }

        $now = time();

        $payload = [
            'iss' => $this->credentials['client_email'],
            'sub' => $this->credentials['client_email'],
            'aud' => self::CUSTOM_TOKEN_AUDIENCE,
            'iat' => $now,
            'exp' => $now + 3600, // 1h, per Firebase custom-token spec.
            'uid' => $uid,
        ];

        if ($claims !== []) {
            $payload['claims'] = $claims;
        }

        try {
            return $this->signRs256($payload);
        } catch (Throwable $e) {
            Log::warning('Firebase: failed to mint custom token.', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Send an FCM (HTTP v1) push notification to each of the supplied device
     * tokens. Never throws — individual failures are logged and skipped.
     *
     * No-op (empty summary) when Firebase is not configured.
     *
     * @param  list<string>  $deviceTokens
     * @param  array<string, string|int|float|bool|null>  $data  Optional data payload (values are coerced to strings).
     * @return array{sent: int, failed: int, invalid_tokens: list<string>}
     */
    public function sendToTokens(array $deviceTokens, string $title, string $body, array $data = []): array
    {
        $summary = ['sent' => 0, 'failed' => 0, 'invalid_tokens' => []];

        $deviceTokens = array_values(array_unique(array_filter($deviceTokens)));

        if ($this->credentials === null || $deviceTokens === []) {
            return $summary;
        }

        $accessToken = $this->accessToken();

        if ($accessToken === null) {
            // Could not authenticate to Google — treat the whole batch as
            // failed without throwing so notification delivery still succeeds.
            $summary['failed'] = count($deviceTokens);

            return $summary;
        }

        $endpoint = sprintf(
            'https://fcm.googleapis.com/v1/projects/%s/messages:send',
            $this->credentials['project_id'],
        );

        $stringData = $this->stringifyData($data);

        foreach ($deviceTokens as $token) {
            $this->sendSingle($endpoint, $accessToken, $token, $title, $body, $stringData, $summary);
        }

        return $summary;
    }

    /**
     * Deliver to one token, mutating the running summary. Invalid/unregistered
     * tokens (HTTP 404 or UNREGISTERED) are collected so the caller can prune
     * them from storage.
     *
     * @param  array<string, string>  $data
     * @param  array{sent: int, failed: int, invalid_tokens: list<string>}  $summary
     */
    private function sendSingle(
        string $endpoint,
        string $accessToken,
        string $token,
        string $title,
        string $body,
        array $data,
        array &$summary,
    ): void {
        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->post($endpoint, [
                    'message' => [
                        'token' => $token,
                        'notification' => [
                            'title' => $title,
                            'body' => $body,
                        ],
                        'data' => $data,
                    ],
                ]);

            if ($response->successful()) {
                $summary['sent']++;

                return;
            }

            $summary['failed']++;

            if ($this->isInvalidTokenResponse($response->status(), (string) $response->body())) {
                $summary['invalid_tokens'][] = $token;
            }

            Log::warning('Firebase: FCM send failed for a token.', [
                'status' => $response->status(),
                'error' => $response->json('error.status') ?? $response->body(),
            ]);
        } catch (Throwable $e) {
            $summary['failed']++;
            Log::warning('Firebase: FCM send threw for a token.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Whether an FCM error response indicates the token is no longer valid and
     * should be deleted (HTTP 404, or the UNREGISTERED / INVALID_ARGUMENT
     * error statuses FCM returns for stale tokens).
     */
    private function isInvalidTokenResponse(int $status, string $body): bool
    {
        if ($status === 404) {
            return true;
        }

        return str_contains($body, 'UNREGISTERED')
            || str_contains($body, 'NOT_FOUND')
            || str_contains($body, 'registration-token-not-registered');
    }

    /**
     * A cached Google OAuth2 access token (service-account JWT-bearer grant)
     * scoped for FCM. Returns null on any failure — callers degrade gracefully.
     */
    private function accessToken(): ?string
    {
        $cached = Cache::get(self::ACCESS_TOKEN_CACHE_KEY);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $token = $this->requestAccessToken();

        if ($token !== null) {
            Cache::put(self::ACCESS_TOKEN_CACHE_KEY, $token, self::ACCESS_TOKEN_TTL_SECONDS);
        }

        return $token;
    }

    /**
     * Exchange a self-signed service-account JWT for a Google access token.
     */
    private function requestAccessToken(): ?string
    {
        if ($this->credentials === null) {
            return null;
        }

        try {
            $now = time();

            $assertion = $this->signRs256([
                'iss' => $this->credentials['client_email'],
                'scope' => self::FCM_SCOPE,
                'aud' => self::GOOGLE_TOKEN_URI,
                'iat' => $now,
                'exp' => $now + 3600,
            ]);

            $response = Http::asForm()->post(self::GOOGLE_TOKEN_URI, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);

            if ($response->successful()) {
                $accessToken = $response->json('access_token');

                return is_string($accessToken) ? $accessToken : null;
            }

            Log::warning('Firebase: OAuth2 token request failed.', [
                'status' => $response->status(),
                'error' => $response->json('error') ?? $response->body(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Firebase: OAuth2 token request threw.', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * RS256-sign a JWT payload with the service-account private key. Uses
     * firebase/php-jwt when installed, otherwise falls back to a hand-rolled
     * openssl_sign so the feature works without the optional dependency.
     *
     * @param  array<string, mixed>  $payload
     */
    private function signRs256(array $payload): string
    {
        $privateKey = $this->credentials['private_key'] ?? '';

        if (class_exists(\Firebase\JWT\JWT::class)) {
            return \Firebase\JWT\JWT::encode($payload, $privateKey, 'RS256');
        }

        $header = ['alg' => 'RS256', 'typ' => 'JWT'];

        $signingInput = $this->base64UrlEncode($this->jsonEncode($header))
            .'.'.$this->base64UrlEncode($this->jsonEncode($payload));

        $signature = '';

        if (openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256) === false) {
            throw new \RuntimeException('Firebase: openssl_sign failed (invalid private key?).');
        }

        return $signingInput.'.'.$this->base64UrlEncode($signature);
    }

    /**
     * Resolve the service account from config. Direct env fields take
     * precedence; any missing field is backfilled from the optional
     * service-account JSON file. Returns null unless all three are present.
     *
     * @return array{project_id: string, client_email: string, private_key: string}|null
     */
    private function resolveCredentials(): ?array
    {
        /** @var array<string, mixed> $config */
        $config = (array) config('services.firebase', []);

        $projectId = $this->stringOrNull($config['project_id'] ?? null);
        $clientEmail = $this->stringOrNull($config['client_email'] ?? null);
        $privateKey = $this->stringOrNull($config['private_key'] ?? null);

        $fromFile = $this->credentialsFromFile($this->stringOrNull($config['credentials'] ?? null));

        $projectId ??= $fromFile['project_id'] ?? null;
        $clientEmail ??= $fromFile['client_email'] ?? null;
        $privateKey ??= $fromFile['private_key'] ?? null;

        if ($projectId === null || $clientEmail === null || $privateKey === null) {
            return null;
        }

        return [
            'project_id' => $projectId,
            'client_email' => $clientEmail,
            // .env stores the key on one line with literal "\n" — restore real
            // newlines so OpenSSL accepts the PEM block.
            'private_key' => str_replace('\n', "\n", $privateKey),
        ];
    }

    /**
     * Load and decode an optional service-account JSON file.
     *
     * @return array{project_id?: string, client_email?: string, private_key?: string}
     */
    private function credentialsFromFile(?string $path): array
    {
        if ($path === null || ! is_file($path) || ! is_readable($path)) {
            return [];
        }

        try {
            $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            Log::warning('Firebase: could not parse credentials file.', ['error' => $e->getMessage()]);

            return [];
        }

        if (! is_array($decoded)) {
            return [];
        }

        return array_filter([
            'project_id' => $this->stringOrNull($decoded['project_id'] ?? null),
            'client_email' => $this->stringOrNull($decoded['client_email'] ?? null),
            'private_key' => $this->stringOrNull($decoded['private_key'] ?? null),
        ], static fn ($value): bool => $value !== null);
    }

    /**
     * FCM data payloads must be a flat string→string map.
     *
     * @param  array<string, string|int|float|bool|null>  $data
     * @return array<string, string>
     */
    private function stringifyData(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }

            $result[(string) $key] = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }

        return $result;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function jsonEncode(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
