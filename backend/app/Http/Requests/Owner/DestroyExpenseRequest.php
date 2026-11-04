<?php

declare(strict_types=1);

namespace App\Http\Requests\Owner;

use App\Services\ExpenseService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Deleting is a void once Finance v2 is on, and a void needs its reason; with
 * the flag off the legacy DELETE takes no body.
 */
class DestroyExpenseRequest extends FormRequest
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
        $workspace = $this->user()?->workspace;
        $voids = $workspace !== null && app(ExpenseService::class)->usesSoftVoid($workspace);

        return [
            'reason' => [$voids ? 'required' : 'nullable', 'string', 'max:500'],
        ];
    }
}
