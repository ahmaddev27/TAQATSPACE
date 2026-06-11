<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AboutContentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AboutController extends Controller
{
    public function __construct(
        private readonly AboutContentService $about,
    ) {}

    /**
     * GET /api/about-content — public About page content (or {} when unset).
     */
    public function show(): JsonResponse
    {
        return ApiResponse::success($this->about->presented());
    }
}
