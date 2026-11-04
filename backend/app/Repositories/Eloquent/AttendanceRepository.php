<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\AttendanceDaily;
use App\Models\HotspotSession;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Reads sessions and rewrites attendance_daily in bulk: no query inside a loop. */
class AttendanceRepository
{
    private const WRITE_CHUNK = 500;

    /**
     * Sessions of subscribers that touch [`$from`, `$to`): they started before
     * the window ends and have not ended before it starts.
     *
     * @return Collection<int, HotspotSession>
     */
    public function sessionsTouching(string $workspaceId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return HotspotSession::query()
            ->withoutWorkspaceScope()
            ->where('workspace_id', $workspaceId)
            ->whereNotNull('subscriber_id')
            ->where('started_at', '<', $to)
            ->where(fn (Builder $query) => $query->whereNull('ended_at')->orWhere('ended_at', '>=', $from))
            ->get(['subscriber_id', 'started_at', 'ended_at', 'last_seen_at']);
    }

    /**
     * Deletes the workspace's rows of the date range and inserts `$rows` in one transaction,
     * so readers never see a half-rebuilt range.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function replaceRange(string $workspaceId, string $fromDate, string $toDate, array $rows): void
    {
        DB::transaction(function () use ($workspaceId, $fromDate, $toDate, $rows): void {
            AttendanceDaily::query()
                ->withoutWorkspaceScope()
                ->where('workspace_id', $workspaceId)
                ->whereBetween('date', [$fromDate, $toDate])
                ->delete();

            foreach (array_chunk($rows, self::WRITE_CHUNK) as $chunk) {
                AttendanceDaily::query()->withoutWorkspaceScope()->insert($chunk);
            }
        });
    }
}
