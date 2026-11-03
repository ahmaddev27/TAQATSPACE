<?php

declare(strict_types=1);

use App\Http\Controllers\Owner\WorkspaceResetController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Workspace danger zone
|--------------------------------------------------------------------------
| Owner-only destructive actions, each behind a recent two-factor check.
*/
Route::middleware(['auth:sanctum', 'role.owner', '2fa.enrolled', '2fa.recent'])
    ->prefix('workspace/danger')
    ->group(function (): void {
        Route::delete('/reset', [WorkspaceResetController::class, 'destroy']);
    });
