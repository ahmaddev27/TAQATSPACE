<?php

declare(strict_types=1);

use App\Http\Controllers\MessageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Workspace Messaging (M08 — T048)
|--------------------------------------------------------------------------
| Owner-scoped threads, direct messages and broadcasts. Marking a thread as
| read is available to both the owner and the member (resolved by role).
*/

Route::middleware(['auth:sanctum', 'role.owner'])
    ->prefix('workspace/messages')
    ->group(function (): void {
        Route::get('/', [MessageController::class, 'index']);
        Route::post('/', [MessageController::class, 'store']);
        Route::post('/broadcast', [MessageController::class, 'broadcast']);
        Route::get('/{member}', [MessageController::class, 'show']);
    });

// Owner OR member may mark their received messages as read.
Route::middleware(['auth:sanctum'])
    ->put('workspace/messages/read', [MessageController::class, 'markRead']);
