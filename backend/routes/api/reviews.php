<?php

declare(strict_types=1);

use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Reviews (T057)
|--------------------------------------------------------------------------
| Public listing per workspace + authenticated freelancer submission.
*/
Route::get('/workspaces/{workspace}/reviews', [ReviewController::class, 'index']);

Route::middleware(['auth:sanctum', 'role.freelancer'])
    ->group(function (): void {
        Route::post('/reviews', [ReviewController::class, 'store']);
    });
