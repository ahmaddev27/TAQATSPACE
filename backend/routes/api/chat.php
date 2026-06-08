<?php

declare(strict_types=1);

use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Chat (Firebase / Firestore realtime)
|--------------------------------------------------------------------------
| Mints a short-lived Firebase custom token so the authenticated user can sign
| in to Firestore for realtime chat. Returns 503 when Firebase is unconfigured.
*/
Route::middleware('auth:sanctum')
    ->prefix('chat')
    ->group(function (): void {
        Route::post('/token', [ChatController::class, 'token']);
    });
