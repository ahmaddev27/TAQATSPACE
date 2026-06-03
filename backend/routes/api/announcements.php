<?php

declare(strict_types=1);

use App\Http\Controllers\AnnouncementController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Announcements (M09 / T055)
|--------------------------------------------------------------------------
| Owner CRUD over their own workspace's announcements (incl. drafts and
| expired), plus a public listing of live announcements for a workspace.
*/

// Owner management — scoped to the authenticated owner's workspace.
Route::middleware(['auth:sanctum', 'role.owner'])
    ->prefix('workspace/announcements')
    ->group(function (): void {
        Route::get('/', [AnnouncementController::class, 'index']);
        Route::post('/', [AnnouncementController::class, 'store']);
        Route::put('/{announcement}', [AnnouncementController::class, 'update']);
        Route::delete('/{announcement}', [AnnouncementController::class, 'destroy']);
    });

// Public — live, non-expired announcements for an active workspace.
Route::get(
    'workspaces/{workspace}/announcements',
    [AnnouncementController::class, 'publicIndex'],
);
