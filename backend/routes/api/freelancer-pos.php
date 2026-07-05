<?php

declare(strict_types=1);

use App\Http\Controllers\Freelancer\PosController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Freelancer — café / POS ordering
|--------------------------------------------------------------------------
| A freelancer browses the menu of, and places pending orders at, a workspace
| they hold an active subscription to. Settlement happens at the counter, so
| there is no payment endpoint here.
*/
Route::middleware(['auth:sanctum', 'role.freelancer'])
    ->prefix('freelancer/pos')
    ->group(function (): void {
        Route::get('/products', [PosController::class, 'products']);
        Route::get('/orders', [PosController::class, 'index']);
        Route::post('/orders', [PosController::class, 'store']);
    });
