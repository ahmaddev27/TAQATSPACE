<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Workspace;
use App\Support\Tenancy\CurrentWorkspaceContext;
use App\Support\Tenancy\WorkspaceScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant scoping for new models: fills `workspace_id` from the current
 * workspace on create and limits every query to it. Legacy models do not use
 * this trait and keep their explicit workspace filters.
 */
trait BelongsToWorkspace
{
    public static function bootBelongsToWorkspace(): void
    {
        static::addGlobalScope(new WorkspaceScope);

        static::creating(function ($model): void {
            if ($model->workspace_id === null) {
                $model->workspace_id = app(CurrentWorkspaceContext::class)->id();
            }
        });
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @param Builder<static> $query */
    public function scopeWithoutWorkspaceScope(Builder $query): void
    {
        $query->withoutGlobalScope(WorkspaceScope::class);
    }
}
