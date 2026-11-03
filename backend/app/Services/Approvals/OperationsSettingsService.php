<?php

declare(strict_types=1);

namespace App\Services\Approvals;

use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "Submit for approval" for the Operations settings tab (SET-OPS, FLOW-5): turns
 * the submitted form into one approval request per kind of change, never
 * touching live data. A newer pending request replaces an older one of the same
 * type, so the admin only ever sees the owner's latest intent.
 */
class OperationsSettingsService
{
    private const CAPACITY_COLUMNS = ['capacity' => 0, 'enabled' => true];

    private const PRICING_COLUMNS = ['price_monthly' => null, 'price_daily' => null];

    public function __construct(private readonly ApprovalService $approvals) {}

    /**
     * @param  array<string, mixed>  $data  Validated form values; only changed ones create requests.
     * @return Collection<int, ApprovalRequest>
     */
    public function submit(Workspace $workspace, array $data, User $requester): Collection
    {
        $workspace->load('seatTypes');
        $payloads = $this->payloads($workspace, $data);

        if ($payloads === []) {
            return new Collection;
        }

        $requests = DB::transaction(function () use ($workspace, $payloads, $requester): array {
            $this->cancelSuperseded($workspace, array_keys($payloads), $requester);

            $created = [];
            foreach ($payloads as $type => $payload) {
                $created[] = $this->approvals->submit($workspace, ApprovalType::from($type), $payload, $requester);
            }

            return $created;
        });

        return (new Collection($requests))->load(['workspace.seatTypes', 'requester']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, array<string, mixed>>
     */
    private function payloads(Workspace $workspace, array $data): array
    {
        $rows = $data['seat_types'] ?? [];
        $capacity = $this->changedRows($workspace, $rows, self::CAPACITY_COLUMNS);
        $pricing = $this->changedRows($workspace, $rows, self::PRICING_COLUMNS);

        $payloads = [
            ApprovalType::Capacity->value => $this->withoutEmpty([
                'seat_types' => $capacity,
                'seating_mode' => $this->changed($data, 'seating_mode', $workspace->seating_mode?->value),
            ]),
            ApprovalType::Pricing->value => $this->withoutEmpty([
                'seat_types' => $pricing,
                'tax_rate' => $this->changed($data, 'tax_rate', $workspace->tax_rate),
            ]),
            ApprovalType::WorkingHours->value => $this->withoutEmpty([
                'working_hours' => $this->changed($data, 'working_hours', $workspace->working_hours),
            ]),
        ];

        return array_filter($payloads, static fn (array $payload): bool => $payload !== []);
    }

    /**
     * Seat-type rows whose given columns differ from the live row, completed with
     * the live (or default) values of the handler's other columns so the stored
     * request is self-contained.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $columns  Column => default for a type that has no row yet.
     * @return array<int, array<string, mixed>>
     */
    private function changedRows(Workspace $workspace, array $rows, array $columns): array
    {
        $live = $workspace->seatTypes->keyBy(static fn ($row): string => $row->type->value);
        $changed = [];

        foreach ($rows as $row) {
            $existing = $live->get($row['type']);
            $base = [];
            foreach ($columns as $column => $default) {
                $base[$column] = $existing === null ? $default : $existing->{$column};
            }

            $proposed = array_intersect_key($row, $columns) + $base;

            if (! $this->sameValues($base, $proposed)) {
                $changed[] = ['type' => $row['type']] + $proposed;
            }
        }

        return $changed;
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function sameValues(array $before, array $after): bool
    {
        foreach ($before as $column => $value) {
            if (! $this->same($value, $after[$column])) {
                return false;
            }
        }

        return true;
    }

    private function same(mixed $a, mixed $b): bool
    {
        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a === (float) $b;
        }

        return $a === $b;
    }

    /** The submitted value when it differs from the live one, otherwise null. */
    private function changed(array $data, string $key, mixed $live): mixed
    {
        if (! array_key_exists($key, $data) || $data[$key] === null) {
            return null;
        }

        return $this->same($data[$key], $live) || $data[$key] == $live ? null : $data[$key];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withoutEmpty(array $payload): array
    {
        return array_filter($payload, static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * @param  list<string>  $types
     */
    private function cancelSuperseded(Workspace $workspace, array $types, User $requester): void
    {
        ApprovalRequest::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', ApprovalStatus::Pending->value)
            ->whereIn('type', $types)
            ->get()
            ->each(fn (ApprovalRequest $pending) => $this->approvals->cancel($pending, $requester));
    }
}
