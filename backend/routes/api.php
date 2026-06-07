<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\OnboardingController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Health
|--------------------------------------------------------------------------
*/
Route::get('/health', [HealthController::class, 'index']);
Route::get('/health/redis', [HealthController::class, 'redis']);

/*
|--------------------------------------------------------------------------
| Authentication (M02)
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function (): void {
    Route::post('/register', [RegisterController::class, 'store']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [PasswordResetController::class, 'forgot'])
        ->middleware('throttle:3,60');
    Route::post('/reset-password', [PasswordResetController::class, 'reset']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/complete-onboarding', [OnboardingController::class, 'complete']);
    });
});

/*
|--------------------------------------------------------------------------
| Feature route modules (M04+)
|--------------------------------------------------------------------------
| Each file in routes/api/*.php self-registers its routes (with its own
| middleware + prefixes). This keeps features decoupled — no shared edits
| to this file when adding a module.
*/
foreach (glob(__DIR__.'/api/*.php') as $routeModule) {
    require $routeModule;
}
