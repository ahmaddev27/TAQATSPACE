<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a site-wide CMS payload. The body is `{ "content": <blob> }` where
 * the blob is a free-form, bilingual, partial-override array whose shape varies
 * per content key (site / faq / about / how_it_works). The nested structure is
 * intentionally unconstrained — the frontend supplies i18n defaults for
 * anything omitted, so partial saves must pass. Authorization is handled by the
 * route middleware (auth:sanctum + role.admin).
 */
class UpdateSiteContentRequest extends FormRequest
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
            'content' => ['nullable', 'array'],
        ];
    }

    /**
     * The content blob to persist, defaulting to an empty array when omitted.
     *
     * @return array<string, mixed>
     */
    public function content(): array
    {
        return $this->validated('content') ?? [];
    }
}
