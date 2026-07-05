<?php

declare(strict_types=1);

use App\Http\Controllers\Pos\OrderController;
use App\Http\Controllers\Pos\ProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| POS (café) — operational endpoints (owner + cashier)
|--------------------------------------------------------------------------
| Shared by a workspace owner and its cashiers. The controller resolves the
| actor's workspace (owner's own, or the cashier's bound workspace) and gates
| mutations behind the `pos_manage_products` permission. Freelancers/guests
| have no workspace here and are rejected (403).
*/
Route::middleware(['auth:sanctum'])
    ->prefix('pos')
    ->group(function (): void {
        Route::get('/products', [ProductController::class, 'index']);
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{product}', [ProductController::class, 'update']);
        Route::delete('/products/{product}', [ProductController::class, 'destroy']);
        Route::post('/products/{product}/stock', [ProductController::class, 'adjustStock']);

        Route::get('/summary', [OrderController::class, 'summary']);
        Route::get('/orders', [OrderController::class, 'index']);
        Route::post('/orders', [OrderController::class, 'store']);
        Route::get('/orders/{order}', [OrderController::class, 'show']);
        Route::post('/orders/{order}/pay', [OrderController::class, 'pay']);
        Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
    });
