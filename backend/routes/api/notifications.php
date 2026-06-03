<?php

declare(strict_types=1);

use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Notifications (M08 — T051, T052)
|--------------------------------------------------------------------------
| In-app notification feed for every authenticated user, backed by Laravel's
| database notifications table.
*/

Route::middleware(['auth:sanctum'])
    ->prefix('notifications')
    ->group(function (): void {
        Route::get('/', [NotificationController::class, 'index']);
        Route::put('/read', [NotificationController::class, 'markRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
    });
