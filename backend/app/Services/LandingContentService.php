<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SiteSetting;

/**
 * Reads and persists the public landing page content (the single 'landing'
 * site setting). Content is a free-form, partial-override bilingual array;
 * the frontend falls back to its own i18n defaults for anything missing.
 */
class LandingContentService
{
    private const KEY = 'landing';

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
     * Upsert the landing content and return the saved array.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public function update(array $content): array
    {
        $setting = SiteSetting::updateOrCreate(
            ['key' => self::KEY],
            ['value' => $content],
        );

        return $setting->value ?? [];
    }
}
