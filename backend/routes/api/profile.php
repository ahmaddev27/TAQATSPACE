<?php

declare(strict_types=1);

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Self-service Profile
|--------------------------------------------------------------------------
| Shared by every authenticated role (admin / freelancer / owner). The user
| always acts on their OWN account — identity comes from the bearer token, so
| no role gate or ownership check is needed beyond authentication.
*/
Route::middleware('auth:sanctum')
    ->prefix('profile')
    ->group(function (): void {
        Route::get('/', [ProfileController::class, 'show']);
        Route::put('/', [ProfileController::class, 'update']);
        Route::put('/password', [ProfileController::class, 'updatePassword']);
    });
