<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SiteContentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SiteContentController extends Controller
{
    public function __construct(
        private readonly SiteContentService $content,
    ) {}

    /**
     * GET /api/content/{key} — public site-wide content for a whitelisted key
     * (or [] when unset). Unknown keys 404.
     */
    public function show(string $key): JsonResponse
    {
        if (! $this->content->supports($key)) {
            throw new NotFoundHttpException("Unknown content key [{$key}].");
        }

        return ApiResponse::success($this->content->get($key));
    }
}
