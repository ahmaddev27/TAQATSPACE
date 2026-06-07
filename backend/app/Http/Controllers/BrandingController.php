<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\BrandingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class BrandingController extends Controller
{
    public function __construct(
        private readonly BrandingService $branding,
    ) {}

    /**
     * GET /api/branding — public site branding (site name, meta, and the
     * resolved favicon / logo URLs) so the app can render it. Returns {} when
     * nothing is set, so the app falls back to its built-in branding.
     */
    public function show(): JsonResponse
    {
        return ApiResponse::success($this->branding->presented());
    }
}
