<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\About\UploadAboutImageRequest;
use App\Services\AboutContentService;
use App\Services\FileUploadService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAboutController extends Controller
{
    public function __construct(
        private readonly AboutContentService $about,
    ) {}

    /**
     * GET /api/admin/about — current about content (or {} when unset).
     */
    public function show(): JsonResponse
    {
        return ApiResponse::success($this->about->presented());
    }

    /**
     * PUT /api/admin/about — upsert the about content (free-form bilingual blob,
     * partial overrides). The frontend may send the blob directly or wrapped in
     * a `content` key; either is accepted and persisted as-is.
     */
    public function update(Request $request): JsonResponse
    {
        $content = $request->input('content', $request->all());
        $content = is_array($content) ? $content : [];

        $saved = $this->about->update($content);

        return ApiResponse::success($saved, __('messages.about_content_updated'));
    }

    /**
     * POST /api/admin/about/images — store a section image and return its
     * canonical path (for round-tripping into the content) plus a display URL.
     */
    public function uploadImage(
        UploadAboutImageRequest $request,
        FileUploadService $uploads,
    ): JsonResponse {
        $stored = $this->about->storeImage($request->file('image'), $uploads);

        return ApiResponse::success($stored, __('messages.about_image_uploaded'), 201);
    }
}
