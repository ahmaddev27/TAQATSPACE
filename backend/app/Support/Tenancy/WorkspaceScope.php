<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Restricts a query to the current workspace. Fails closed: with no workspace
 * in the context nothing is returned, so a missing context can never leak
 * another tenant's rows. Use `withoutWorkspaceScope()` for admin and jobs.
 */
final class WorkspaceScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $workspaceId = app(CurrentWorkspaceContext::class)->id();

        if ($workspaceId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn('workspace_id'), $workspaceId);
    }
}
