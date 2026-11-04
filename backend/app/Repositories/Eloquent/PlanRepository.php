<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Plan;
use App\Models\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Plan lookups. Every query names the workspace explicitly (on top of the
 * model's tenancy scope) so a plan of another workspace is never returned.
 */
class PlanRepository
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Plan>
     */
    public function paginate(Workspace $workspace, array $filters): LengthAwarePaginator
    {
        $query = Plan::query()->where('workspace_id', $workspace->id);

        match ($filters['archived'] ?? 'exclude') {
            'only' => $query->whereNotNull('archived_at'),
            'include' => null,
            default => $query->whereNull('archived_at'),
        };

        if (! empty($filters['pricing_method'])) {
            $query->where('pricing_method', $filters['pricing_method']);
        }

        $this->applySearch($query, $filters['search'] ?? null);

        $perPage = max(1, min((int) ($filters['per_page'] ?? 50), 200));

        return $query->orderBy('code')->paginate($perPage)->withQueryString();
    }

    public function find(Workspace $workspace, string $id): Plan
    {
        return Plan::query()->where('workspace_id', $workspace->id)->find($id)
            ?? throw (new ModelNotFoundException)->setModel(Plan::class, [$id]);
    }

    /**
     * @param  Builder<Plan>  $query
     */
    private function applySearch(Builder $query, mixed $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';
        $query->where(fn (Builder $inner) => $inner
            ->where('code', 'like', $like)
            ->orWhere('name_ar', 'like', $like)
            ->orWhere('name_en', 'like', $like));
    }
}
