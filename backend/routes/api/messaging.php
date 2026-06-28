<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\BroadcastController as AdminBroadcastController;
use App\Http\Controllers\Admin\MessagingSettingsController;
use App\Http\Controllers\Admin\MessagingUsageController;
use App\Http\Controllers\Owner\BroadcastController as OwnerBroadcastController;
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
Route::middleware(['auth:sanctum', 'role.admin', 'can.permission:manage_messaging'])
    ->prefix('admin/settings/messaging')
    ->group(function (): void {
        Route::get('/', [MessagingSettingsController::class, 'show']);
        Route::put('/', [MessagingSettingsController::class, 'update']);
        Route::post('/test', [MessagingSettingsController::class, 'test']);
    });

// Compose & broadcast (email and/or SMS) to a chosen audience — Super Admin
// sends through the platform accounts.
Route::middleware(['auth:sanctum', 'role.admin', 'can.permission:manage_messaging'])
    ->post('/admin/messaging/broadcast', [AdminBroadcastController::class, 'store']);

// Platform-quota usage per workspace (who sends via Taqat's accounts) — Super Admin.
Route::middleware(['auth:sanctum', 'role.admin', 'can.permission:manage_messaging'])
    ->get('/admin/messaging/usage', [MessagingUsageController::class, 'index']);

// Per-workspace messaging — Workspace Owner.
Route::middleware(['auth:sanctum', 'role.owner'])
    ->put('/workspace/messaging', [MessagingController::class, 'update']);

// Compose & broadcast to the owner's OWN workspace members — sent through the
// workspace's own accounts (or the platform when `use_platform`).
Route::middleware(['auth:sanctum', 'role.owner'])
    ->post('/workspace/messaging/broadcast', [OwnerBroadcastController::class, 'store']);
