<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a single branding-asset upload (favicon or a light/dark logo).
 *
 * Accepts the raster formats used elsewhere plus SVG (logos/favicons are often
 * vector). Authorization is handled by the route middleware (auth:sanctum +
 * role.admin).
 */
class UploadBrandingImageRequest extends FormRequest
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
            // SVG is not an image() per GD, so validate by extension/mime only.
            'image' => ['required', 'file', 'mimes:png,jpg,jpeg,webp,svg,ico', 'max:4096'],
        ];
    }
}
