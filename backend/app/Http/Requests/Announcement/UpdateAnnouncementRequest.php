<?php

declare(strict_types=1);

namespace App\Http\Requests\Announcement;

use App\Enums\AnnouncementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Role enforcement is handled by the route middleware (role.owner).
        // Ownership of the announcement is enforced in the controller/service.
        return true;
    }

    /**
     * Partial update: every field is optional but validated when present.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'required', Rule::in([
                AnnouncementType::Offer->value,
                AnnouncementType::Info->value,
                AnnouncementType::Alert->value,
            ])],
            'title' => ['sometimes', 'required', 'string', 'min:2', 'max:150'],
            'body' => ['sometimes', 'required', 'string', 'max:5000'],
            'published_at' => ['sometimes', 'nullable', 'date'],
            'expires_at' => ['sometimes', 'nullable', 'date', 'after:published_at'],
        ];
    }
}
