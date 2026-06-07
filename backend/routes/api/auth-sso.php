<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\TaqatSsoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| "Sign in with Taqat" — OIDC SSO (public)
|--------------------------------------------------------------------------
| Browser-driven OIDC Authorization Code + PKCE flow. All routes are public:
| /redirect and /callback are part of the browser round-trip, and /exchange
| redeems a short-lived one-time code (not a bearer token) for the real token.
*/
Route::prefix('auth/taqat')->group(function (): void {
    Route::get('/redirect', [TaqatSsoController::class, 'redirect']);
    Route::get('/callback', [TaqatSsoController::class, 'callback']);
    Route::post('/exchange', [TaqatSsoController::class, 'exchange']);
});
