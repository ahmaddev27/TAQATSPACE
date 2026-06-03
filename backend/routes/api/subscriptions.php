<?php

declare(strict_types=1);

use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Member subscriptions (T034)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'role.freelancer'])->prefix('member/subscriptions')->group(function (): void {
    Route::get('/', [SubscriptionController::class, 'index']);
    Route::get('/{subscription}', [SubscriptionController::class, 'show']);
    Route::put('/{subscription}/cancel', [SubscriptionController::class, 'cancel']);
});
