<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SiteSetting;
use App\Support\MediaUrl;
use Illuminate\Http\UploadedFile;

/**
 * Reads and persists the public landing page content (the single 'landing'
 * site setting). Content is a free-form, partial-override bilingual array;
 * the frontend falls back to its own i18n defaults for anything missing.
 *
 * Per-section images are stored as media-disk PATHS under the `*.image` keys.
 * When the content is read for display, each stored path is resolved to a
 * viewable URL and exposed alongside it as a sibling `*.imageUrl` (the editor
 * previews the URL while round-tripping the path; the public page renders the
 * URL). The raw path stays in the payload so saves are idempotent.
 *
 * @phpstan-type LandingArray array<string, mixed>
 */
class LandingContentService
{
    private const KEY = 'landing';

    /**
     * Dot-paths of the section image fields, in the stored content shape. The
     * resolved URL is written to a sibling key with an `Url` suffix on the
     * final segment (e.g. `hero.image` -> `hero.imageUrl`).
     *
     * @var list<string>
     */
    private const IMAGE_PATHS = [
        'hero.image',
        'sections.why.image',
    ];

    /**
     * The stored landing content, or an empty array when nothing is set yet.
     *
     * @return array<string, mixed>
     */
    public function get(): array
    {
        $setting = SiteSetting::query()->where('key', self::KEY)->first();

        return $setting?->value ?? [];
    }

    /**
     * The stored content with each image path resolved to a sibling display
     * URL. Use this for the GET endpoints (admin editor + public page).
     *
     * @return array<string, mixed>
     */
    public function presented(): array
    {
        return $this->withResolvedImageUrls($this->get());
    }

    /**
     * Upsert the landing content and return the saved (presented) array.
     *
     * Resolved `*.imageUrl` keys are display-only and never persisted, so they
     * are stripped before saving to keep the stored JSON to canonical paths.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public function update(array $content): array
    {
        $content = $this->withoutResolvedImageUrls($content);

        $setting = SiteSetting::updateOrCreate(
            ['key' => self::KEY],
            ['value' => $content],
        );

        return $this->withResolvedImageUrls($setting->value ?? []);
    }

    /**
     * Store an uploaded landing image on the media disk and return both the
     * canonical storage path (for round-tripping into the content) and the
     * resolved display URL.
     *
     * @return array{path: string, url: string}
     */
    public function storeImage(UploadedFile $file, FileUploadService $uploads): array
    {
        $disk = (string) config('filesystems.media', 'public');
        $path = $uploads->upload($file, 'landing', $disk, 'public');

        return [
            'path' => $path,
            'url' => MediaUrl::resolve($path) ?? $path,
        ];
    }

    /**
     * Return a copy of $content with a resolved `*.imageUrl` added next to each
     * non-empty `*.image` path. Missing paths are left untouched.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function withResolvedImageUrls(array $content): array
    {
        foreach (self::IMAGE_PATHS as $path) {
            $value = data_get($content, $path);

            if (is_string($value) && $value !== '') {
                $url = MediaUrl::resolve($value);
                if ($url !== null) {
                    data_set($content, $this->urlPath($path), $url);
                }
            }
        }

        return $content;
    }

    /**
     * Strip every resolved `*.imageUrl` key so only canonical paths persist.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function withoutResolvedImageUrls(array $content): array
    {
        foreach (self::IMAGE_PATHS as $path) {
            data_forget($content, $this->urlPath($path));
        }

        return $content;
    }

    /** Sibling display-URL key for an image path (`hero.image` -> `hero.imageUrl`). */
    private function urlPath(string $imagePath): string
    {
        return preg_replace('/\.image$/', '.imageUrl', $imagePath) ?? $imagePath;
    }
}
