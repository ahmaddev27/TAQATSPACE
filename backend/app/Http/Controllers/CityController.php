<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\CityResource;
use App\Services\CityService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class CityController extends Controller
{
    public function __construct(
        private readonly CityService $cities,
    ) {}

    /**
     * GET /api/cities — the public list of active cities.
     */
    public function index(): JsonResponse
    {
        return ApiResponse::success(
            CityResource::collection($this->cities->list(activeOnly: true)),
        );
    }
}
