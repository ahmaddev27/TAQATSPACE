<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a site-branding payload: the site name, the SEO meta (title +
 * description) and the three brand image PATHS (favicon, light/dark logos).
 *
 * Every field is optional — the app falls back to its built-in branding for
 * anything the admin leaves unset. Image fields carry the canonical media-disk
 * path returned by the branding image-upload endpoint (a string), not the file
 * itself. Authorization is handled by the route middleware (auth:sanctum +
 * role.admin).
 */
class UpdateBrandingRequest extends FormRequest
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
            'site_name' => ['nullable', 'string', 'max:120'],
            'meta_title' => ['nullable', 'string', 'max:160'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'favicon' => ['nullable', 'string', 'max:2048'],
            'logo_light' => ['nullable', 'string', 'max:2048'],
            'logo_dark' => ['nullable', 'string', 'max:2048'],
        ];
    }

    /**
     * The sanitized branding fields to persist.
     *
     * @return array<string, mixed>
     */
    public function branding(): array
    {
        return $this->validated();
    }
}
