<?php

declare(strict_types=1);

namespace App\Http\Requests\Landing;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the bilingual landing-page CMS payload. Every field is optional
 * (partial overrides) so any subset of the schema is accepted; the frontend
 * supplies i18n defaults for anything omitted. Authorization is handled by the
 * route middleware (auth:sanctum + role.admin).
 */
class UpdateLandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // --- Section order (admin-chosen order of the reorderable sections) ---
            'sections_order' => ['sometimes', 'nullable', 'array'],
            'sections_order.*' => ['string', 'max:50'],

            // --- Hero ---
            'hero' => ['sometimes', 'nullable', 'array'],
            ...$this->bilingual('hero.title'),
            ...$this->bilingual('hero.highlight'),
            ...$this->bilingual('hero.subtitle', 1000),
            ...$this->bilingual('hero.ctaPrimary'),
            ...$this->bilingual('hero.ctaSecondary'),
            // Section image: stored media PATH (uploaded via POST .../images).
            // The resolved display URL (`imageUrl`) is added by the service on
            // read and accepted here so a round-tripped payload still validates.
            'hero.image' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'hero.imageUrl' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'hero.imageSecondary' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'hero.imageSecondaryUrl' => ['sometimes', 'nullable', 'string', 'max:2000'],

            // --- Stats (max 4) ---
            'stats' => ['sometimes', 'nullable', 'array', 'max:4'],
            'stats.*.value' => ['nullable', 'string', 'max:500'],
            ...$this->bilingual('stats.*.label'),

            // --- Sections ---
            'sections' => ['sometimes', 'nullable', 'array'],

            'sections.featured' => ['sometimes', 'nullable', 'array'],
            'sections.featured.enabled' => ['nullable', 'boolean'],
            ...$this->bilingual('sections.featured.title'),
            ...$this->bilingual('sections.featured.subtitle', 1000),
            'sections.featured.workspaceIds' => ['nullable', 'array', 'max:6'],
            'sections.featured.workspaceIds.*' => ['uuid', 'exists:workspaces,id'],

            'sections.why' => ['sometimes', 'nullable', 'array'],
            'sections.why.enabled' => ['nullable', 'boolean'],
            ...$this->bilingual('sections.why.title'),
            ...$this->bilingual('sections.why.highlight'),
            ...$this->bilingual('sections.why.subtitle', 1000),
            'sections.why.image' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'sections.why.imageUrl' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'sections.why.imageSecondary' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'sections.why.imageSecondaryUrl' => ['sometimes', 'nullable', 'string', 'max:2000'],

            'sections.capabilities' => ['sometimes', 'nullable', 'array'],
            'sections.capabilities.enabled' => ['nullable', 'boolean'],
            ...$this->bilingual('sections.capabilities.title'),
            ...$this->bilingual('sections.capabilities.subtitle', 1000),

            'sections.testimonials' => ['sometimes', 'nullable', 'array'],
            'sections.testimonials.enabled' => ['nullable', 'boolean'],
            ...$this->bilingual('sections.testimonials.title'),

            // --- Testimonials (max 12) ---
            'testimonials' => ['sometimes', 'nullable', 'array', 'max:12'],
            ...$this->bilingual('testimonials.*.text', 1000),
            'testimonials.*.name' => ['nullable', 'string', 'max:500'],
            ...$this->bilingual('testimonials.*.role'),
        ];
    }

    /**
     * Rule pair for a bilingual `{ar, en}` leaf object — both halves are
     * optional nullable strings capped at $max characters.
     *
     * @return array<string, array<int, string>>
     */
    private function bilingual(string $key, int $max = 500): array
    {
        return [
            "{$key}.ar" => ['nullable', 'string', "max:{$max}"],
            "{$key}.en" => ['nullable', 'string', "max:{$max}"],
        ];
    }
}
