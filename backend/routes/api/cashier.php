<?php

declare(strict_types=1);

use App\Http\Controllers\Cashier\InvitationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Cashier — invitation accept/decline (POS module)
|--------------------------------------------------------------------------
| Authenticated (SSO) onboarding actions: an invitee who has signed in to
| Taqat accepts or declines a cashier invitation addressed to their verified
| email. The signed-in account is the credential — there is no token link.
*/
Route::middleware(['auth:sanctum'])
    ->prefix('cashier/invitations')
    ->group(function (): void {
        Route::post('/{invitation}/accept', [InvitationController::class, 'accept']);
        Route::post('/{invitation}/decline', [InvitationController::class, 'decline']);
    });
