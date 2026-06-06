<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landing\UpdateLandingRequest;
use App\Http\Requests\Landing\UploadLandingImageRequest;
use App\Services\FileUploadService;
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
        return ApiResponse::success($this->landing->presented());
    }

    /**
     * PUT /api/admin/landing — upsert the landing content (partial overrides).
     */
    public function update(UpdateLandingRequest $request): JsonResponse
    {
        $saved = $this->landing->update($request->validated());

        return ApiResponse::success($saved, __('messages.landing_content_updated'));
    }

    /**
     * POST /api/admin/landing/images — store a section image and return its
     * canonical path (for round-tripping into the content) plus a display URL.
     */
    public function uploadImage(
        UploadLandingImageRequest $request,
        FileUploadService $uploads,
    ): JsonResponse {
        $stored = $this->landing->storeImage($request->file('image'), $uploads);

        return ApiResponse::success($stored, __('messages.landing_image_uploaded'), 201);
    }
}
