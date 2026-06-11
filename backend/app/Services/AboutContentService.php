<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SiteSetting;
use App\Support\MediaUrl;
use Illuminate\Http\UploadedFile;

/**
 * Reads and persists the public About page content (the single 'about' site
 * setting). Content is a free-form, partial-override bilingual array; the
 * frontend falls back to its own i18n defaults for anything missing.
 *
 * Images are stored as media-disk PATHS under the top-level `image` /
 * `imageSecondary` keys and under each `sections[i].image`. When the content is
 * read for display, every stored path is resolved to a viewable URL and exposed
 * alongside it as a sibling `*Url` key (the editor previews the URL while
 * round-tripping the path; the public page renders the URL). The raw path stays
 * in the payload so saves are idempotent.
 *
 * @phpstan-type AboutArray array<string, mixed>
 */
class AboutContentService
{
    private const KEY = 'about';

    /**
     * Dot-paths of the top-level (flat) image fields. The resolved URL is written
     * to a sibling key by appending `Url` to the path (e.g. `image` -> `imageUrl`,
     * `imageSecondary` -> `imageSecondaryUrl`). Per-section images live in the
     * dynamic `sections` array and are resolved separately by index.
     *
     * @var list<string>
     */
    private const IMAGE_PATHS = [
        'image',
        'imageSecondary',
    ];

    /**
     * The stored about content, or an empty array when nothing is set yet.
     *
     * @return array<string, mixed>
     */
    public function get(): array
    {
        $setting = SiteSetting::query()->where('key', self::KEY)->first();

        return $setting?->value ?? [];
    }

    /**
     * The stored content with each image path resolved to a sibling display URL.
     * Use this for the GET endpoints (admin editor + public page).
     *
     * @return array<string, mixed>
     */
    public function presented(): array
    {
        return $this->withResolvedImageUrls($this->get());
    }

    /**
     * Upsert the about content and return the saved (presented) array.
     *
     * Resolved `*Url` keys are display-only and never persisted, so they are
     * stripped before saving to keep the stored JSON to canonical paths.
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
     * Store an uploaded about image on the media disk and return both the
     * canonical storage path (for round-tripping into the content) and the
     * resolved display URL.
     *
     * @return array{path: string, url: string}
     */
    public function storeImage(UploadedFile $file, FileUploadService $uploads): array
    {
        $disk = (string) config('filesystems.media', 'public');
        $path = $uploads->upload($file, 'about', $disk, 'public');

        return [
            'path' => $path,
            'url' => MediaUrl::resolve($path) ?? $path,
        ];
    }

    /**
     * Return a copy of $content with a resolved `*Url` added next to each
     * non-empty image path: the flat top-level paths plus every per-section
     * `sections[i].image`. Missing paths are left untouched.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function withResolvedImageUrls(array $content): array
    {
        foreach (self::IMAGE_PATHS as $path) {
            $content = $this->resolveOne($content, $path);
        }

        foreach ($this->sectionImagePaths($content) as $path) {
            $content = $this->resolveOne($content, $path);
        }

        return $content;
    }

    /**
     * Strip every resolved `*Url` key (flat + per-section) so only canonical
     * paths persist.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function withoutResolvedImageUrls(array $content): array
    {
        foreach (self::IMAGE_PATHS as $path) {
            data_forget($content, $this->urlPath($path));
        }

        foreach ($this->sectionImagePaths($content) as $path) {
            data_forget($content, $this->urlPath($path));
        }

        return $content;
    }

    /**
     * Resolve a single image path to its sibling display URL when present.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function resolveOne(array $content, string $path): array
    {
        $value = data_get($content, $path);

        if (is_string($value) && $value !== '') {
            $url = MediaUrl::resolve($value);
            if ($url !== null) {
                data_set($content, $this->urlPath($path), $url);
            }
        }

        return $content;
    }

    /**
     * The dot-paths of every per-section image field present in the content
     * (e.g. `sections.0.image`, `sections.1.image`).
     *
     * @param  array<string, mixed>  $content
     * @return list<string>
     */
    private function sectionImagePaths(array $content): array
    {
        $sections = data_get($content, 'sections');

        if (! is_array($sections)) {
            return [];
        }

        $paths = [];
        foreach (array_keys($sections) as $index) {
            $paths[] = "sections.{$index}.image";
        }

        return $paths;
    }

    /** Sibling display-URL key for an image path (`image` -> `imageUrl`). */
    private function urlPath(string $imagePath): string
    {
        return $imagePath.'Url';
    }
}
