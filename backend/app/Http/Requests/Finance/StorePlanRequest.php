<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use Illuminate\Validation\Rule;

class StorePlanRequest extends PlanRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => [
                'required', 'string', 'max:50', 'regex:/^[A-Za-z0-9][A-Za-z0-9_-]*$/',
                Rule::unique('plans', 'code')->where('workspace_id', $this->workspaceId()),
            ],
            ...$this->planRules(),
        ];
    }
}
