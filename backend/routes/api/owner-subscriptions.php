<?php

declare(strict_types=1);

use App\Http\Controllers\Owner\SubscriptionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Owner Subscriptions Management
|--------------------------------------------------------------------------
| Track, renew, and cancel the subscriptions of the authenticated owner's
| workspace. All actions are scoped to the owner's workspace — foreign
| subscriptions are never listed or mutated.
*/
Route::middleware(['auth:sanctum', 'role.owner'])
    ->prefix('workspace/subscriptions')
    ->group(function (): void {
        Route::get('/', [SubscriptionController::class, 'index']);
        Route::put('/{subscription}/renew', [SubscriptionController::class, 'renew']);
        Route::put('/{subscription}/cancel', [SubscriptionController::class, 'cancel']);
        Route::post('/{subscription}/internet', [SubscriptionController::class, 'provisionInternet']);
    });
