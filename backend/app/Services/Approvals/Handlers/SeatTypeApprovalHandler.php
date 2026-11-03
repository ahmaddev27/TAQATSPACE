<?php

declare(strict_types=1);

namespace App\Services\Approvals\Handlers;

use App\Contracts\ApprovalHandler;
use App\Enums\SeatType;
use App\Models\ApprovalRequest;
use App\Models\Workspace;
use App\Services\WorkspaceService;
use Illuminate\Validation\Rule;

/**
 * Shared plumbing for the handlers that edit a subset of the per-seat-type
 * columns: each subclass names the columns it owns, so a capacity request can
 * never overwrite prices and vice versa.
 */
abstract class SeatTypeApprovalHandler implements ApprovalHandler
{
    public function __construct(protected readonly WorkspaceService $workspaces) {}

    /** @return list<string> The `seat_type_prices` columns this handler may change. */
    abstract protected function columns(): array;

    /** @return array<string, mixed> Validation rules for one `seat_types.*` row (besides `type`). */
    abstract protected function columnRules(): array;

    /**
     * @param  string  $sibling  The handler's other field; a request needs at least one of the two.
     * @return array<string, mixed>
     */
    protected function seatTypeRules(string $sibling): array
    {
        $rules = ['seat_types' => ["required_without:{$sibling}", 'array', 'min:1', 'max:'.count(SeatType::cases())]];
        $rules['seat_types.*.type'] = ['required', Rule::in(SeatType::values()), 'distinct'];

        foreach ($this->columnRules() as $column => $rule) {
            $rules["seat_types.*.{$column}"] = $rule;
        }

        return $rules;
    }

    protected function applySeatTypes(ApprovalRequest $request): void
    {
        $rows = $request->payload['seat_types'] ?? null;

        if ($rows !== null) {
            $this->workspaces->patchSeatTypes($request->workspace, $rows);
        }
    }

    /**
     * The workspace's live values for exactly the types and columns the request touches.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function currentSeatTypes(ApprovalRequest $request): array
    {
        $proposed = $request->payload['seat_types'] ?? [];
        $live = $this->liveRows($request->workspace);

        return array_map(function (array $row) use ($live): array {
            $existing = $live[$row['type']] ?? [];

            return ['type' => $row['type']] + array_intersect_key($existing, array_flip($this->columns()));
        }, $proposed);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function liveRows(?Workspace $workspace): array
    {
        $rows = [];

        foreach ($workspace?->seatTypes ?? [] as $row) {
            $rows[$row->type->value] = [
                'price_monthly' => $row->price_monthly,
                'price_daily' => $row->price_daily,
                'capacity' => $row->capacity,
                'enabled' => $row->enabled,
            ];
        }

        return $rows;
    }
}
