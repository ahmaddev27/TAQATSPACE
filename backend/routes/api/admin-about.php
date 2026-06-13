<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AdminAboutController;
use App\Http\Controllers\AboutController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| About Page CMS — public read + admin manage
|--------------------------------------------------------------------------
| The public About page reads its (bilingual, partial-override) content here;
| a Super Admin edits it via the admin endpoints. Mirrors the landing CMS.
*/

// Public read.
Route::get('/about-content', [AboutController::class, 'show']);

// Admin manage.
Route::middleware(['auth:sanctum', 'role.admin', 'can.permission:manage_content'])
    ->prefix('admin/about')
    ->group(function (): void {
        Route::get('/', [AdminAboutController::class, 'show']);
        Route::put('/', [AdminAboutController::class, 'update']);
        Route::post('/images', [AdminAboutController::class, 'uploadImage']);
    });
