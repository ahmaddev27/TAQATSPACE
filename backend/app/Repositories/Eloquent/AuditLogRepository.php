<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filtered, paginated reads of the audit log. Actors are eager-loaded in one
 * batch so a page never costs more than two queries.
 */
class AuditLogRepository
{
    /**
     * @param  array<string, mixed>  $filters  actor_id, action (`a.b` exact, `a.*` prefix), from, to, subject_type, subject_id, reason, per_page; workspace_id only when $workspaceId is null
     */
    public function paginate(?string $workspaceId, array $filters): LengthAwarePaginator
    {
        $workspaceId ??= $filters['workspace_id'] ?? null;

        $query = AuditLog::query()
            ->with('actor:id,name,email')
            ->when($workspaceId, fn (Builder $q, string $id) => $q->where('workspace_id', $id));

        $this->applyFilters($query, $filters);

        return $query->orderByDesc('id')->paginate(min((int) ($filters['per_page'] ?? 25), 100));
    }

    /**
     * @param  Builder<AuditLog>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $query
            ->when($filters['actor_id'] ?? null, fn (Builder $q, string $v) => $q->where('actor_id', $v))
            ->when($filters['subject_type'] ?? null, fn (Builder $q, string $v) => $q->where('subject_type', $v))
            ->when($filters['subject_id'] ?? null, fn (Builder $q, string $v) => $q->where('subject_id', $v))
            ->when($filters['from'] ?? null, fn (Builder $q, string $v) => $q->where('created_at', '>=', $v.' 00:00:00'))
            ->when($filters['to'] ?? null, fn (Builder $q, string $v) => $q->where('created_at', '<=', $v.' 23:59:59'))
            ->when($filters['reason'] ?? null, fn (Builder $q, string $v) => $q->where('reason', 'like', '%'.$this->escapeLike($v).'%'))
            ->when($filters['action'] ?? null, fn (Builder $q, string $v) => $this->applyAction($q, $v));
    }

    /**
     * @param  Builder<AuditLog>  $query
     */
    private function applyAction(Builder $query, string $action): void
    {
        if (str_ends_with($action, '*')) {
            $query->where('action', 'like', $this->escapeLike(rtrim($action, '*')).'%');

            return;
        }

        $query->where('action', $action);
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
    }
}
