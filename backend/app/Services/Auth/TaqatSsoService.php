<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * "Sign in with Taqat" — OIDC Authorization Code + PKCE flow.
 *
 * Endpoints are discovered from {issuer}/.well-known/openid-configuration and
 * cached. We rely on the /userinfo endpoint for claims (the access_token is the
 * authority), so no id_token JWT validation / extra crypto dependency is needed.
 */
class TaqatSsoService
{
    private const DISCOVERY_TTL = 3600;          // 1 hour

    private const STATE_TTL = 600;               // 10 minutes

    private const DISCOVERY_CACHE_KEY = 'sso:discovery';

    /**
     * How long an SSO-session marker lives. Bounded so the cache cannot grow
     * unbounded; it only needs to outlive an active browser session long enough
     * to power single logout, after which a normal local logout is harmless.
     */
    private const SESSION_TTL = 60 * 60 * 24 * 7; // 7 days (matches token cookie)

    private const SESSION_CACHE_PREFIX = 'sso:session:';

    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    /**
     * Build the IdP /authorize URL. Generates a CSRF `state` and a PKCE
     * `code_verifier`, derives the S256 `code_challenge`, and stashes the
     * verifier in the cache keyed by state so the callback can complete PKCE.
     *
     * @return array{url: string, state: string}
     */
    public function buildAuthorizeUrl(): array
    {
        $config = $this->config();
        $endpoints = $this->discover();

        $state = Str::random(40);
        $codeVerifier = Str::random(96);
        $codeChallenge = $this->codeChallenge($codeVerifier);

        Cache::put("sso:state:{$state}", ['verifier' => $codeVerifier], self::STATE_TTL);

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $config['client_id'],
            'redirect_uri' => $config['redirect_uri'],
            'scope' => 'openid profile email phone',
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);

        return [
            'url' => $endpoints['authorization_endpoint'].'?'.$query,
            'state' => $state,
        ];
    }

    /**
     * Validate state, complete the PKCE token exchange, then fetch the user's
     * claims from /userinfo.
     *
     * The raw `id_token` from the token response is returned alongside the
     * claims so the caller can keep it server-side for RP-initiated single
     * logout (`id_token_hint`). It is never exposed to the browser.
     *
     * @return array{claims: array<string, mixed>, id_token: ?string}
     *
     * @throws RuntimeException on any invalid/expired state or IdP failure
     */
    public function exchangeCode(string $code, string $state): array
    {
        $stored = Cache::pull("sso:state:{$state}");

        if (! is_array($stored) || ! isset($stored['verifier'])) {
            throw new RuntimeException('Invalid or expired SSO state.');
        }

        $config = $this->config();
        $endpoints = $this->discover();

        $tokenResponse = Http::asForm()->post($endpoints['token_endpoint'], [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $config['redirect_uri'],
            'code_verifier' => $stored['verifier'],
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
        ]);

        if ($tokenResponse->failed()) {
            throw new RuntimeException('Token exchange failed.');
        }

        $accessToken = $tokenResponse->json('access_token');

        if (! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('No access token returned by the identity provider.');
        }

        $idToken = $tokenResponse->json('id_token');

        $userinfoResponse = Http::withToken($accessToken)
            ->acceptJson()
            ->get($endpoints['userinfo_endpoint']);

        if ($userinfoResponse->failed()) {
            throw new RuntimeException('Failed to fetch userinfo.');
        }

        $claims = $userinfoResponse->json();

        if (! is_array($claims) || ! isset($claims['sub'])) {
            throw new RuntimeException('Userinfo response missing subject claim.');
        }

        return [
            'claims' => $claims,
            'id_token' => is_string($idToken) && $idToken !== '' ? $idToken : null,
        ];
    }

    /**
     * Mark a freshly-issued Sanctum access token as SSO-backed and stash the
     * `id_token` for later single logout. Keyed by the token's server-side id
     * (never the plaintext token), the id_token never reaches the browser.
     */
    public function rememberSession(int|string $accessTokenId, ?string $idToken): void
    {
        Cache::put(
            self::SESSION_CACHE_PREFIX.$accessTokenId,
            ['id_token' => $idToken],
            self::SESSION_TTL,
        );
    }

    /**
     * Build the IdP RP-initiated logout (end-session) URL for an SSO-backed
     * Sanctum token, then forget the stored session marker. Returns null when
     * the token was not SSO-backed, the provider exposes no end_session_endpoint,
     * or discovery is unavailable — the caller then performs a plain local
     * logout with no hard failure.
     */
    public function buildLogoutUrl(int|string $accessTokenId): ?string
    {
        $session = Cache::pull(self::SESSION_CACHE_PREFIX.$accessTokenId);
        $idToken = is_array($session) && is_string($session['id_token'] ?? null)
            ? $session['id_token']
            : null;

        // Build the logout URL even when the session marker is missing (cache
        // evicted / different store / TTL) — only id_token_hint depends on it.
        $endpoint = $this->endSessionEndpoint();

        // TEMP DEBUG (SSO single-logout diagnosis) — remove once verified.
        // Dedicated file at debug level so it bypasses LOG_LEVEL / the default
        // channel: storage/logs/sso-logout.log.
        Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/sso-logout.log'),
            'level' => 'debug',
        ])->info('[sso-logout] buildLogoutUrl', [
            'access_token_id' => $accessTokenId,
            'session_found' => is_array($session),
            'has_id_token' => $idToken !== null,
            'config_end_session_endpoint' => $this->config()['end_session_endpoint'] ?? null,
            'config_post_logout_redirect_uri' => $this->config()['post_logout_redirect_uri'] ?? null,
            'config_client_id' => $this->config()['client_id'] ?? null,
            'resolved_endpoint' => $endpoint,
            'cache_store' => config('cache.default'),
        ]);

        if ($endpoint === null) {
            return null;
        }

        $config = $this->config();

        $params = array_filter([
            'client_id' => $config['client_id'],
            'post_logout_redirect_uri' => $this->postLogoutRedirectUri(),
            'id_token_hint' => $idToken,
        ], static fn ($value): bool => $value !== null && $value !== '');

        return $endpoint.'?'.http_build_query($params);
    }

    /**
     * The IdP's RP-initiated logout endpoint from discovery, or null when the
     * provider does not advertise one.
     */
    private function endSessionEndpoint(): ?string
    {
        // Explicit override first — for IdPs whose discovery omits
        // end_session_endpoint (e.g. Laravel Passport does not advertise OIDC
        // RP-initiated logout). Set TAQAT_SSO_END_SESSION_URL to the IdP's
        // logout route in that case.
        $override = $this->config()['end_session_endpoint'] ?? null;
        if (is_string($override) && $override !== '') {
            return $override;
        }

        try {
            $endpoints = $this->discover();
        } catch (RuntimeException) {
            // Discovery unavailable — degrade gracefully to a local-only logout.
            return null;
        }

        $endpoint = $endpoints['end_session_endpoint'] ?? null;

        return is_string($endpoint) && $endpoint !== '' ? $endpoint : null;
    }

    /**
     * Where the IdP returns the browser after ending the SSO session. Falls
     * back to the configured redirect_uri host's frontend when unset.
     */
    private function postLogoutRedirectUri(): string
    {
        $configured = $this->config()['post_logout_redirect_uri'] ?? null;

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return rtrim((string) config('app.frontend_url'), '/').'/login?loggedout=1';
    }

    /**
     * Find an existing user (by sso_sub, then email) or provision a new one.
     * Backfills sso_sub / phone / name on an email-matched account.
     *
     * @param  array<string, mixed>  $claims
     */
    public function findOrProvisionUser(array $claims): User
    {
        $sub = (string) $claims['sub'];
        $email = isset($claims['email']) ? (string) $claims['email'] : null;
        $name = $this->resolveName($claims, $email);
        $phone = $this->resolvePhone($claims);

        $existing = $this->users->findBySsoSub($sub)
            ?? ($email !== null ? $this->users->findByEmail($email) : null);

        if ($existing !== null) {
            return $this->backfill($existing, $sub, $name, $phone);
        }

        if ($email === null) {
            throw new RuntimeException('Userinfo response missing email claim.');
        }

        return $this->provision($sub, $email, $name, $phone);
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function resolveName(array $claims, ?string $email): string
    {
        if (isset($claims['name']) && is_string($claims['name']) && trim($claims['name']) !== '') {
            return trim($claims['name']);
        }

        if ($email !== null) {
            return Str::before($email, '@');
        }

        return 'Taqat User';
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function resolvePhone(array $claims): ?string
    {
        foreach (['phone_number', 'phone'] as $key) {
            if (isset($claims[$key]) && is_string($claims[$key]) && $claims[$key] !== '') {
                return $claims[$key];
            }
        }

        return null;
    }

    private function backfill(User $user, string $sub, string $name, ?string $phone): User
    {
        $updates = [];

        if ($user->sso_sub === null) {
            $updates['sso_sub'] = $sub;
        }

        if (($user->phone === null || $user->phone === '') && $phone !== null) {
            $updates['phone'] = $phone;
        }

        if (($user->name === null || $user->name === '') && $name !== '') {
            $updates['name'] = $name;
        }

        if ($updates !== []) {
            $user->update($updates);
        }

        return $user;
    }

    private function provision(string $sub, string $email, string $name, ?string $phone): User
    {
        // A freshly-provisioned SSO user has not yet chosen freelancer vs
        // workspace-owner. `role` carries a Freelancer placeholder only to keep
        // the non-null column valid; `onboarding_completed_at = null` is the
        // authoritative "must onboard" signal (status stays pending until then).
        $role = UserRole::Freelancer;

        // The `password` cast hashes this; a random value keeps the row valid
        // while leaving password login disabled for SSO-provisioned accounts.
        $user = $this->users->create([
            'name' => $name,
            'email' => $email,
            'password' => Str::random(64),
            'phone' => $phone,
            'role' => $role->value,
            'status' => UserStatus::PendingVerification->value,
            'sso_sub' => $sub,
            'onboarding_completed_at' => null,
            'email_verified_at' => now(),
        ]);

        $user->assignRole($role->value);

        return $user;
    }

    /**
     * Discover (and cache) the IdP's authoritative endpoints.
     *
     * @return array{authorization_endpoint: string, token_endpoint: string, userinfo_endpoint: string, end_session_endpoint?: string}
     */
    private function discover(): array
    {
        $issuer = rtrim((string) $this->config()['issuer'], '/');

        if ($issuer === '') {
            throw new RuntimeException('Taqat SSO issuer is not configured.');
        }

        return Cache::remember(self::DISCOVERY_CACHE_KEY, self::DISCOVERY_TTL, function () use ($issuer): array {
            $response = Http::acceptJson()->get("{$issuer}/.well-known/openid-configuration");

            if ($response->failed()) {
                throw new RuntimeException('Failed to fetch the SSO discovery document.');
            }

            $document = $response->json();

            foreach (['authorization_endpoint', 'token_endpoint', 'userinfo_endpoint'] as $required) {
                if (! isset($document[$required]) || ! is_string($document[$required])) {
                    throw new RuntimeException("Discovery document missing {$required}.");
                }
            }

            return $document;
        });
    }

    /**
     * Base64url(SHA-256(verifier)) — the PKCE S256 code challenge.
     */
    private function codeChallenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    /**
     * @return array{issuer: ?string, client_id: string, client_secret: ?string, redirect_uri: ?string, post_logout_redirect_uri: ?string}
     */
    private function config(): array
    {
        /** @var array{issuer: ?string, client_id: string, client_secret: ?string, redirect_uri: ?string, post_logout_redirect_uri: ?string, end_session_endpoint: ?string} $config */
        $config = config('services.taqat_sso');

        return $config;
    }
}
