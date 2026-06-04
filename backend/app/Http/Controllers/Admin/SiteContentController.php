<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSiteContentRequest;
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
     * GET /api/admin/content/{key} — current content for a whitelisted key
     * (or [] when unset). Unknown keys 404.
     */
    public function show(string $key): JsonResponse
    {
        $this->guardKey($key);

        return ApiResponse::success($this->content->get($key));
    }

    /**
     * PUT /api/admin/content/{key} — upsert the content blob (partial
     * overrides) for a whitelisted key. Unknown keys 404.
     */
    public function update(UpdateSiteContentRequest $request, string $key): JsonResponse
    {
        $this->guardKey($key);

        $saved = $this->content->update($key, $request->content());

        return ApiResponse::success($saved, 'Content updated.');
    }

    /**
     * Reject keys outside the managed-content whitelist with a 404.
     */
    private function guardKey(string $key): void
    {
        if (! $this->content->supports($key)) {
            throw new NotFoundHttpException("Unknown content key [{$key}].");
        }
    }
}
