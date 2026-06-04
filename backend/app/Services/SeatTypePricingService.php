<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SeatType;
use App\Models\SeatTypePrice;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

/**
 * Owns the per-(workspace, seat type) pricing rows. Each row is upserted by its
 * natural key (workspace_id, type) so callers may submit a partial set without
 * disturbing the types they omit.
 */
class SeatTypePricingService
{
    /**
     * Upsert the given pricing rows for a workspace.
     *
     * @param  array<int, array{type: string, price_monthly?: float|string|null, price_daily?: float|string|null, capacity?: int|null, enabled?: bool|null}>  $rows
     */
    public function sync(Workspace $workspace, array $rows): void
    {
        DB::transaction(function () use ($workspace, $rows): void {
            foreach ($rows as $row) {
                $this->upsertRow($workspace, $row);
            }
        });
    }

    /**
     * @param  array{type: string, price_monthly?: float|string|null, price_daily?: float|string|null, capacity?: int|null, enabled?: bool|null}  $row
     */
    private function upsertRow(Workspace $workspace, array $row): SeatTypePrice
    {
        return $workspace->seatTypes()->updateOrCreate(
            ['type' => SeatType::from($row['type'])->value],
            [
                'price_monthly' => $row['price_monthly'] ?? null,
                'price_daily' => $row['price_daily'] ?? null,
                'capacity' => (int) ($row['capacity'] ?? 0),
                'enabled' => (bool) ($row['enabled'] ?? true),
            ],
        );
    }
}
