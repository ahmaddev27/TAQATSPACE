<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Validation\ValidationException;

/**
 * Reads and persists site-wide CMS content stored as keyed `site_settings`
 * rows. Each value is a free-form, partial-override bilingual array; the
 * frontend supplies its own i18n fallbacks for anything missing.
 *
 * Mirrors {@see LandingContentService} but works over a whitelist of generic
 * content keys instead of the single 'landing' key.
 */
class SiteContentService
{
    /**
     * The content keys a Super Admin is allowed to read and edit.
     *
     * @var list<string>
     */
    public const KEYS = ['site', 'faq', 'about', 'how_it_works'];

    /**
     * Whether the given key is part of the managed-content whitelist.
     */
    public function supports(string $key): bool
    {
        return in_array($key, self::KEYS, true);
    }

    /**
     * The stored content for the key, or an empty array when nothing is set yet.
     *
     * @return array<string, mixed>
     */
    public function get(string $key): array
    {
        $this->guardKey($key);

        $setting = SiteSetting::query()->where('key', $key)->first();

        return $setting?->value ?? [];
    }

    /**
     * Upsert the content for the key and return the saved array.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public function update(string $key, array $content): array
    {
        $this->guardKey($key);

        $setting = SiteSetting::updateOrCreate(
            ['key' => $key],
            ['value' => $content],
        );

        return $setting->value ?? [];
    }

    /**
     * Reject keys outside the whitelist so unknown content can never be
     * written to (or read from) arbitrary `site_settings` rows.
     */
    private function guardKey(string $key): void
    {
        if (! $this->supports($key)) {
            throw ValidationException::withMessages([
                'key' => "Unknown content key [{$key}].",
            ]);
        }
    }
}
