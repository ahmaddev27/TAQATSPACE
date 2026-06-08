<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateBrandingRequest;
use App\Http\Requests\Admin\UploadBrandingImageRequest;
use App\Services\BrandingService;
use App\Services\FileUploadService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class BrandingController extends Controller
{
    public function __construct(
        private readonly BrandingService $branding,
    ) {}

    /**
     * GET /api/admin/branding — current branding (or {} when unset), with each
     * image path resolved to a sibling display URL.
     */
    public function show(): JsonResponse
    {
        return ApiResponse::success($this->branding->presented());
    }

    /**
     * PUT /api/admin/branding — upsert the branding (site name, meta, and the
     * favicon / light / dark logo paths).
     */
    public function update(UpdateBrandingRequest $request): JsonResponse
    {
        $saved = $this->branding->update($request->branding());

        return ApiResponse::success($saved, __('messages.branding_updated'));
    }

    /**
     * POST /api/admin/branding/images — store a branding asset (favicon or a
     * logo) and return its canonical path plus a resolved display URL.
     */
    public function uploadImage(
        UploadBrandingImageRequest $request,
        FileUploadService $uploads,
    ): JsonResponse {
        $stored = $this->branding->storeImage($request->file('image'), $uploads);

        return ApiResponse::success($stored, __('messages.branding_image_uploaded'), 201);
    }
}
