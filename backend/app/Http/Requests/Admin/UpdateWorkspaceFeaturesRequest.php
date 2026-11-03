<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\WorkspaceFeature;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a partial feature-flag update. Authorization is enforced by the
 * route middleware (auth:sanctum + role.admin + can.permission).
 */
class UpdateWorkspaceFeaturesRequest extends FormRequest
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
        $rules = ['features' => ['required', 'array', 'min:1']];

        foreach (WorkspaceFeature::values() as $feature) {
            $rules["features.$feature"] = ['sometimes', 'boolean'];
        }

        return $rules;
    }

    /**
     * Only known features, as booleans (unknown keys are ignored).
     *
     * @return array<string, bool>
     */
    public function changes(): array
    {
        $features = (array) $this->validated('features');

        return array_map('boolval', array_intersect_key(
            $features,
            array_flip(WorkspaceFeature::values()),
        ));
    }
}
