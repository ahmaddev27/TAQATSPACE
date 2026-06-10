<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\ContactMessageController;
use App\Http\Controllers\ContactController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Contact us
|--------------------------------------------------------------------------
| Public submission + the admin inbox (gated by the messaging permission).
*/

// Public contact form — rate-limited to deter spam.
Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:5,60');

Route::middleware(['auth:sanctum', 'role.admin', 'can.permission:manage_messaging'])
    ->prefix('admin/contact-messages')
    ->group(function (): void {
        Route::get('/', [ContactMessageController::class, 'index']);
        Route::put('/{contactMessage}/read', [ContactMessageController::class, 'markRead']);
        Route::delete('/{contactMessage}', [ContactMessageController::class, 'destroy']);
    });
