<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Super-Admin User Management (Phase-4)
|--------------------------------------------------------------------------
| Filtered user directory and account status moderation.
*/

Route::middleware(['auth:sanctum', 'role.admin', 'can.permission:manage_users'])
    ->prefix('admin/users')
    ->group(function (): void {
        Route::get('/', [UserController::class, 'index']);
        Route::get('/{user}', [UserController::class, 'show']);
        Route::put('/{user}/status', [UserController::class, 'updateStatus']);
    });
