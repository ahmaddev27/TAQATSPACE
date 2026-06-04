<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landing\UpdateLandingRequest;
use App\Services\LandingContentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class LandingController extends Controller
{
    public function __construct(
        private readonly LandingContentService $landing,
    ) {}

    /**
     * GET /api/admin/landing — current landing content (or {} when unset).
     */
    public function show(): JsonResponse
    {
        return ApiResponse::success($this->landing->get());
    }

    /**
     * PUT /api/admin/landing — upsert the landing content (partial overrides).
     */
    public function update(UpdateLandingRequest $request): JsonResponse
    {
        $saved = $this->landing->update($request->validated());

        return ApiResponse::success($saved, 'Landing content updated.');
    }
}
