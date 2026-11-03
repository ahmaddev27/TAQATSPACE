<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Services\Finance\CatalogAccess;
use App\Support\Tenancy\CurrentWorkspaceContext;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base for catalog write requests: only users allowed to manage the catalog
 * pass, and rules can scope lookups to the request's workspace.
 */
abstract class CatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && app(CatalogAccess::class)->canManage($user);
    }

    protected function workspaceId(): ?string
    {
        return app(CurrentWorkspaceContext::class)->id();
    }

    /**
     * @return array<int, string>
     */
    protected function codeRules(): array
    {
        return ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9][A-Za-z0-9_-]*$/'];
    }
}
