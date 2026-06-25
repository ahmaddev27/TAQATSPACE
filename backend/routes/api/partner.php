<?php

declare(strict_types=1);

use App\Http\Controllers\Partner\MembershipController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Partner API (server-to-server)
|--------------------------------------------------------------------------
| Machine-to-machine endpoints for integrated platforms (e.g. Academy).
| Authenticated by `X-Api-Key` against `partner_clients`.
*/

Route::middleware('partner')->prefix('partner')->group(function (): void {
    Route::get('/students/{identifier}/membership', [MembershipController::class, 'show']);
});
