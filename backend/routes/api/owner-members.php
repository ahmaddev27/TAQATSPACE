<?php

declare(strict_types=1);

use App\Http\Controllers\Owner\MemberController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Owner Members Management (T028)
|--------------------------------------------------------------------------
| Roster, detail, and status changes for the authenticated owner's
| workspace members. All actions are scoped to the owner's workspace.
*/
Route::middleware(['auth:sanctum', 'role.owner'])
    ->prefix('workspace/members')
    ->group(function (): void {
        Route::get('/', [MemberController::class, 'index']);
        Route::get('/{user}', [MemberController::class, 'show']);
        Route::put('/{user}/status', [MemberController::class, 'updateStatus']);
    });
