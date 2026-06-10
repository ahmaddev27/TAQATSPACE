<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Public "Contact us" submission. Unauthenticated — anyone may send a message.
 */
class StoreContactRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160'],
            'subject' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'max:4000'],
        ];
    }
}
