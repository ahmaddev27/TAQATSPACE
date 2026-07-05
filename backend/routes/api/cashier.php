<?php

declare(strict_types=1);

use App\Http\Controllers\Pos\InvitationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Cashier — public invitation acceptance (POS module)
|--------------------------------------------------------------------------
| Token-authed, no session: a café/cashier invitee previews and accepts their
| emailed invitation, which provisions their account. Throttled because these
| are unauthenticated, token-guessing-adjacent endpoints.
*/
Route::prefix('cashier')
    ->middleware('throttle:10,1')
    ->group(function (): void {
        Route::get('/invitation', [InvitationController::class, 'show']);
        Route::post('/accept', [InvitationController::class, 'accept']);
    });
