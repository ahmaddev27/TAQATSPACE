<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\MessagingSettingsController;
use App\Http\Controllers\Owner\MessagingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Messaging configuration — platform (admin) + per-workspace (owner)
|--------------------------------------------------------------------------
| The Super Admin configures Taqat's own SMTP + SMS accounts; each workspace
| either inherits those platform accounts or supplies its own. Secrets are
| encrypted at rest and never returned (responses expose `has_*` flags only).
*/

// Platform messaging — Super Admin.
Route::middleware(['auth:sanctum', 'role.admin'])
    ->prefix('admin/settings/messaging')
    ->group(function (): void {
        Route::get('/', [MessagingSettingsController::class, 'show']);
        Route::put('/', [MessagingSettingsController::class, 'update']);
        Route::post('/test', [MessagingSettingsController::class, 'test']);
    });

// Per-workspace messaging — Workspace Owner.
Route::middleware(['auth:sanctum', 'role.owner'])
    ->put('/workspace/messaging', [MessagingController::class, 'update']);
