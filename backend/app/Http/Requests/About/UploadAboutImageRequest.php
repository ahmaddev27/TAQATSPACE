<?php

declare(strict_types=1);

namespace App\Http\Requests\About;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a single About-section image upload. Authorization is handled by
 * the route middleware (auth:sanctum + role.admin).
 */
class UploadAboutImageRequest extends FormRequest
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
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }
}
