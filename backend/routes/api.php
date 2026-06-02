<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\EmailVerificationController;
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
    Route::get('/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed')
        ->name('verification.verify');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/email/resend', [EmailVerificationController::class, 'resend'])
            ->middleware('throttle:6,1');
    });
});

/*
|--------------------------------------------------------------------------
| Role-gated route groups (populated in later phases)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role.owner'])->prefix('workspace')->group(function (): void {
    // Phase 2+: owner dashboard, members, seats, bookings, invoices, ...
});

Route::middleware(['auth:sanctum', 'role.freelancer'])->prefix('member')->group(function (): void {
    // Phase 2+: freelancer dashboard, subscriptions, profile, ...
});

Route::middleware(['auth:sanctum', 'role.admin'])->prefix('admin')->group(function (): void {
    // Phase 4+: analytics, workspace approval, users, commissions, ...
});
