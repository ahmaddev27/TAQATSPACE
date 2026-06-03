<?php

declare(strict_types=1);

namespace App\Http\Requests\Message;

use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
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
            'receiver_id' => ['required', 'uuid', 'exists:users,id'],
            'content' => ['required', 'string', 'min:1', 'max:5000'],
        ];
    }
}
