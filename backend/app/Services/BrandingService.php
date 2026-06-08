<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SiteSetting;
use App\Support\MediaUrl;
use Illuminate\Http\UploadedFile;

/**
 * Reads and persists the site-wide branding (the single 'branding' site
 * setting): the site name, the SEO meta (title + description) and the three
 * brand image PATHS — the favicon and the light / dark logos.
 *
 * Mirrors {@see LandingContentService}: images are stored as media-disk paths
 * under the `*` keys and, when read for display, each stored path is resolved
 * to a viewable URL exposed alongside it as a sibling `*Url` (the editor
 * previews the URL while round-tripping the path; the app renders the URL).
 * The raw path stays in the payload so saves are idempotent.
 *
 * @phpstan-type BrandingArray array<string, mixed>
 */
class BrandingService
{
    private const KEY = 'branding';

    /**
     * The branding fields that store a media-disk path. The resolved display URL
     * is written to a sibling key by appending `Url` (e.g. `logo_light` ->
     * `logo_lightUrl`).
     *
     * @var list<string>
     */
    private const IMAGE_PATHS = ['favicon', 'logo_light', 'logo_dark'];

    /**
     * The free-text (non-image) branding fields the admin may edit.
     *
     * @var list<string>
     */
    private const TEXT_KEYS = ['site_name', 'meta_title', 'meta_description'];

    /**
     * The stored branding, or an empty array when nothing is set yet.
     *
     * @return array<string, mixed>
     */
    public function get(): array
    {
        $setting = SiteSetting::query()->where('key', self::KEY)->first();

        return $setting?->value ?? [];
    }

    /**
     * The stored branding with each image path resolved to a sibling display
     * URL. Use this for every GET endpoint (admin editor + public read).
     *
     * @return array<string, mixed>
     */
    public function presented(): array
    {
        return $this->withResolvedImageUrls($this->get());
    }

    /**
     * Upsert the branding and return the saved (presented) array.
     *
     * Only the whitelisted text + image-path keys are persisted; resolved
     * `*Url` keys are display-only and stripped first so the stored JSON stays
     * canonical.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function update(array $input): array
    {
        $content = $this->sanitize($input);

        $setting = SiteSetting::updateOrCreate(
            ['key' => self::KEY],
            ['value' => $content],
        );

        return $this->withResolvedImageUrls($setting->value ?? []);
    }

    /**
     * Store an uploaded branding asset (favicon / logo) on the media disk and
     * return both the canonical storage path (for round-tripping into the
     * branding) and the resolved display URL.
     *
     * @return array{path: string, url: string}
     */
    public function storeImage(UploadedFile $file, FileUploadService $uploads): array
    {
        $disk = (string) config('filesystems.media', 'public');
        $path = $uploads->upload($file, 'branding', $disk, 'public');

        return [
            'path' => $path,
            'url' => MediaUrl::resolve($path) ?? $path,
        ];
    }

    /**
     * Keep only the whitelisted text + image-path keys, trimming text and
     * dropping empty values so the stored JSON stays clean.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function sanitize(array $input): array
    {
        $content = [];

        foreach (self::TEXT_KEYS as $key) {
            $value = $input[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $content[$key] = trim($value);
            }
        }

        foreach (self::IMAGE_PATHS as $key) {
            $value = $input[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $content[$key] = trim($value);
            }
        }

        return $content;
    }

    /**
     * Return a copy of $content with a resolved `*Url` added next to each
     * non-empty image path. Missing paths are left untouched.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function withResolvedImageUrls(array $content): array
    {
        foreach (self::IMAGE_PATHS as $key) {
            $value = $content[$key] ?? null;

            if (is_string($value) && $value !== '') {
                $url = MediaUrl::resolve($value);
                if ($url !== null) {
                    $content[$key.'Url'] = $url;
                }
            }
        }

        return $content;
    }
}
