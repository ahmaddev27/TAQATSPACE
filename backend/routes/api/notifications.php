<?php

declare(strict_types=1);

use App\Http\Controllers\DeviceTokenController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Notifications (M08 — T051, T052)
|--------------------------------------------------------------------------
| In-app notification feed for every authenticated user, backed by Laravel's
| database notifications table. Device-token endpoints register FCM tokens so
| the same notifications can also be pushed to the user's devices.
*/

Route::middleware(['auth:sanctum'])
    ->prefix('notifications')
    ->group(function (): void {
        Route::get('/', [NotificationController::class, 'index']);
        Route::put('/read', [NotificationController::class, 'markRead']);

        // FCM device tokens — registered before the wildcard {id} route so
        // "device-tokens" is never captured as a notification id.
        Route::post('/device-tokens', [DeviceTokenController::class, 'store']);
        Route::delete('/device-tokens', [DeviceTokenController::class, 'destroy']);

        Route::delete('/{id}', [NotificationController::class, 'destroy']);
    });
