<?php

declare(strict_types=1);

namespace App\Http\Requests\Announcement;

use App\Enums\AnnouncementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Role enforcement is handled by the route middleware (role.owner).
        return true;
    }

    /**
     * The "system" type is reserved for platform-generated notices and is not
     * publicly creatable, so owners may only pick offer|info|alert.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in([
                AnnouncementType::Offer->value,
                AnnouncementType::Info->value,
                AnnouncementType::Alert->value,
            ])],
            'title' => ['required', 'string', 'min:2', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
            'published_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:published_at'],
        ];
    }
}
