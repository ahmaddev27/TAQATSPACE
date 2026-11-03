<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\TwoFactorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| TOTP two-factor authentication
|--------------------------------------------------------------------------
| Owner, staff and admin accounts only (eligibility is checked per action).
| `challenge` is the one route a challenge-only (`2fa-pending`) token can reach.
*/
Route::prefix('auth/two-factor')->middleware('auth:sanctum')->group(function (): void {
    Route::get('/', [TwoFactorController::class, 'status']);
    Route::post('/enable', [TwoFactorController::class, 'enable']);
    Route::post('/confirm', [TwoFactorController::class, 'confirm']);
    Route::post('/challenge', [TwoFactorController::class, 'challenge']);
    Route::post('/verify', [TwoFactorController::class, 'verify']);
    Route::post('/disable', [TwoFactorController::class, 'disable']);
});
