<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\BrandingController as AdminBrandingController;
use App\Http\Controllers\BrandingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Site Branding — public read + admin manage
|--------------------------------------------------------------------------
| The app reads the admin-set branding (site name, SEO meta, favicon, and the
| light/dark logos) here; a Super Admin edits it via the admin endpoints. The
| branding is stored as the single 'branding' `site_settings` row.
*/

// Public read.
Route::get('/branding', [BrandingController::class, 'show']);

// Admin manage.
Route::middleware(['auth:sanctum', 'role.admin'])
    ->prefix('admin/branding')
    ->group(function (): void {
        Route::get('/', [AdminBrandingController::class, 'show']);
        Route::put('/', [AdminBrandingController::class, 'update']);
        Route::post('/images', [AdminBrandingController::class, 'uploadImage']);
    });
