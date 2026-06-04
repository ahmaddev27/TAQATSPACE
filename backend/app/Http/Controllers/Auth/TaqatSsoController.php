<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\TaqatSsoService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * "Sign in with Taqat" (OIDC Authorization Code + PKCE).
 *
 * The browser is redirected to the IdP, returns to /callback, and we then hand
 * a one-time exchange code to the frontend so the Sanctum token never travels
 * in a URL. The SPA redeems that code via /exchange to receive the real token.
 */
class TaqatSsoController extends Controller
{
    private const EXCHANGE_TTL = 60;

    public function __construct(
        private readonly TaqatSsoService $sso,
    ) {}

    /** Kick off the flow: build the authorize URL and redirect the browser to the IdP. */
    public function redirect(): RedirectResponse
    {
        try {
            $authorize = $this->sso->buildAuthorizeUrl();

            return redirect()->away($authorize['url']);
        } catch (Throwable $e) {
            Log::warning('Taqat SSO redirect failed', ['error' => $e->getMessage()]);

            return redirect()->away($this->frontendLoginError());
        }
    }

    /** IdP redirect target: exchange the code, provision the user, bridge a token. */
    public function callback(Request $request): RedirectResponse
    {
        $code = $request->query('code');
        $state = $request->query('state');

        if ($request->query('error') !== null || ! is_string($code) || ! is_string($state)) {
            return redirect()->away($this->frontendLoginError());
        }

        try {
            $claims = $this->sso->exchangeCode($code, $state);
            $user = $this->sso->findOrProvisionUser($claims);

            $token = $user->createToken('sso')->plainTextToken;

            $exchangeCode = Str::random(48);
            Cache::put("sso:exchange:{$exchangeCode}", [
                'token' => $token,
                'role' => $user->role->value,
                'user_id' => $user->id,
            ], self::EXCHANGE_TTL);

            return redirect()->away($this->frontendUrl()."/auth/taqat/finish?code={$exchangeCode}");
        } catch (Throwable $e) {
            Log::warning('Taqat SSO callback failed', ['error' => $e->getMessage()]);

            return redirect()->away($this->frontendLoginError());
        }
    }

    /** Redeem the one-time exchange code for the real token (token leaves the URL here). */
    public function exchange(Request $request): JsonResponse
    {
        $code = $request->input('code');

        if (! is_string($code) || $code === '') {
            return ApiResponse::error('Missing exchange code.', 401);
        }

        $entry = Cache::pull("sso:exchange:{$code}");

        if (! is_array($entry) || ! isset($entry['token'], $entry['user_id'])) {
            return ApiResponse::error('Invalid or expired exchange code.', 401);
        }

        $user = User::query()->find($entry['user_id']);

        if ($user === null) {
            return ApiResponse::error('User not found.', 401);
        }

        return ApiResponse::success([
            'token' => $entry['token'],
            'token_type' => 'Bearer',
            'role' => $entry['role'],
            'user' => new UserResource($user),
        ]);
    }

    private function frontendUrl(): string
    {
        return rtrim((string) config('app.frontend_url'), '/');
    }

    private function frontendLoginError(): string
    {
        return $this->frontendUrl().'/login?sso_error=1';
    }
}
