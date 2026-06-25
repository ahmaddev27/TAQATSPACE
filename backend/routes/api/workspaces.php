<?php

declare(strict_types=1);

use App\Http\Controllers\PhotoController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Workspaces — discovery (public), management (owner), admin (M04 / T023-T025)
|--------------------------------------------------------------------------
*/

// Public discovery.
Route::prefix('workspaces')->group(function (): void {
    Route::get('/', [WorkspaceController::class, 'index']);
    Route::get('/cities', [WorkspaceController::class, 'cities']);
    Route::get('/{workspace}', [WorkspaceController::class, 'show']);
});

// Owner-managed workspace (single workspace resolved from the authenticated owner).
Route::middleware(['auth:sanctum', 'role.owner'])
    ->prefix('workspace')
    ->group(function (): void {
        Route::post('/create', [WorkspaceController::class, 'store']);
        Route::put('/settings', [WorkspaceController::class, 'update']);
        Route::post('/photos', [PhotoController::class, 'store']);
        Route::delete('/photos', [PhotoController::class, 'destroy']);
    });

// Admin moderation.
Route::middleware(['auth:sanctum', 'role.admin', 'can.permission:manage_workspaces'])
    ->prefix('admin/workspaces')
    ->group(function (): void {
        Route::get('/', [WorkspaceController::class, 'adminIndex']);
        Route::get('/{workspace}', [WorkspaceController::class, 'adminShow']);
        Route::put('/{workspace}/status', [WorkspaceController::class, 'adminUpdateStatus']);
        Route::put('/{workspace}/publish', [WorkspaceController::class, 'adminPublish']);
        Route::put('/{workspace}/unpublish', [WorkspaceController::class, 'adminUnpublish']);
    });
