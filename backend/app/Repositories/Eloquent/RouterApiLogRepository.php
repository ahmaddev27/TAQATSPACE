<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Router;
use App\Models\RouterApiLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/** Filtered, paginated reads of one router's API request log, newest first. */
class RouterApiLogRepository
{
    /**
     * @param  array<string, mixed>  $filters  from, to (dates), response_code, error (substring), per_page
     */
    public function paginate(Router $router, array $filters): LengthAwarePaginator
    {
        $query = RouterApiLog::query()
            ->where('router_id', $router->getKey())
            ->when($filters['from'] ?? null, fn (Builder $q, string $v) => $q->where('created_at', '>=', $v.' 00:00:00'))
            ->when($filters['to'] ?? null, fn (Builder $q, string $v) => $q->where('created_at', '<=', $v.' 23:59:59'))
            ->when($filters['response_code'] ?? null, fn (Builder $q, $v) => $q->where('response_code', (int) $v))
            ->when($filters['error'] ?? null, fn (Builder $q, string $v) => $q->where('error_message', 'like', '%'.addcslashes($v, '\\%_').'%'));

        return $query->orderByDesc('id')->paginate(min((int) ($filters['per_page'] ?? 25), 100));
    }
}
