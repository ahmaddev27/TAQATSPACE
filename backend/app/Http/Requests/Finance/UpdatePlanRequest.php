<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

class UpdatePlanRequest extends PlanRequest
{
    /**
     * `code` is fixed at creation: other documents key off it.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['code' => ['prohibited'], ...$this->planRules()];
    }
}
