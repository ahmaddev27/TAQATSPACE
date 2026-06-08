<?php

declare(strict_types=1);

use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Chat (Firebase / Firestore realtime)
|--------------------------------------------------------------------------
| - POST /token: mints a short-lived Firebase custom token so the authenticated
|   user can sign in to Firestore for realtime chat (503 when unconfigured).
| - GET  /contacts: the user's chat-able contacts, scoped to their role
|   (owner → members, freelancer → workspace owner[s]).
*/
Route::middleware('auth:sanctum')
    ->prefix('chat')
    ->group(function (): void {
        Route::post('/token', [ChatController::class, 'token']);
        Route::get('/contacts', [ChatController::class, 'contacts']);
    });
