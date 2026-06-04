<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\LandingContentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class LandingController extends Controller
{
    public function __construct(
        private readonly LandingContentService $landing,
    ) {}

    /**
     * GET /api/landing — public landing page content (or {} when unset).
     */
    public function show(): JsonResponse
    {
        return ApiResponse::success($this->landing->get());
    }
}
