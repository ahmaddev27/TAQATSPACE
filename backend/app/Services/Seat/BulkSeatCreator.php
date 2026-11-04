<?php

declare(strict_types=1);

namespace App\Services\Seat;

use App\Enums\SeatKind;
use App\Enums\SeatStatus;
use App\Models\Seat;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates many stations in one atomic operation (`prefix` + a running number).
 * The whole batch is rejected when any generated number is already taken or the
 * workspace capacity would be exceeded; it costs a fixed number of queries
 * however many stations are added.
 */
class BulkSeatCreator
{
    public const MAX_COUNT = 200;

    /**
     * @param  array{start: int, count: int, prefix?: string|null, pad?: int|null, type: string, kind?: string|null, zone?: string|null, branch_id?: string|null, is_private?: bool|null, capacity?: int|null}  $data
     * @return Collection<int, Seat>
     */
    public function create(Workspace $workspace, array $data, ?string $defaultBranchId): Collection
    {
        $numbers = $this->numbers($data);

        return DB::transaction(function () use ($workspace, $data, $numbers, $defaultBranchId): Collection {
            // Serialises concurrent bulk adds on the same workspace so the duplicate check holds.
            Workspace::query()->whereKey($workspace->id)->lockForUpdate()->first();

            $this->assertCapacity($workspace, count($numbers));
            $this->assertNoDuplicates($workspace, $numbers);

            $rows = $this->rows($workspace, $data, $numbers, $defaultBranchId);
            Seat::query()->insert($rows);

            return Seat::query()
                ->where('workspace_id', $workspace->id)
                ->whereIn('id', array_column($rows, 'id'))
                ->orderBy('seat_number')
                ->get();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function numbers(array $data): array
    {
        $prefix = (string) ($data['prefix'] ?? '');
        $pad = (int) ($data['pad'] ?? 0);
        $numbers = [];

        for ($i = 0; $i < (int) $data['count']; $i++) {
            $numbers[] = $prefix.str_pad((string) ((int) $data['start'] + $i), $pad, '0', STR_PAD_LEFT);
        }

        return $numbers;
    }

    private function assertCapacity(Workspace $workspace, int $adding): void
    {
        $current = Seat::query()->where('workspace_id', $workspace->id)->count();

        if ($current + $adding > $workspace->total_seats) {
            abort(422, __('messages.seat_capacity_reached'));
        }
    }

    /** @param list<string> $numbers */
    private function assertNoDuplicates(Workspace $workspace, array $numbers): void
    {
        $taken = Seat::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('seat_number', $numbers)
            ->orderBy('seat_number')
            ->pluck('seat_number');

        if ($taken->isNotEmpty()) {
            abort(422, __('messages.seat_bulk_numbers_exist', [
                'numbers' => $taken->take(5)->implode(', '),
            ]));
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $numbers
     * @return list<array<string, mixed>>
     */
    private function rows(Workspace $workspace, array $data, array $numbers, ?string $defaultBranchId): array
    {
        $now = now();

        return array_map(static fn (string $number): array => [
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'seat_number' => $number,
            'type' => $data['type'],
            'status' => SeatStatus::Available->value,
            'kind' => $data['kind'] ?? SeatKind::Desk->value,
            'zone' => $data['zone'] ?? null,
            'capacity' => (int) ($data['capacity'] ?? 1),
            'is_private' => (bool) ($data['is_private'] ?? false),
            'branch_id' => $data['branch_id'] ?? $defaultBranchId,
            'created_at' => $now,
            'updated_at' => $now,
        ], $numbers);
    }
}
