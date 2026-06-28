<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Workspace;
use App\Services\WorkspaceService;
use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Builder;

/**
 * Aggregates everything an admin needs to see about one workspace on its detail
 * page: profile, owner, seat types + occupancy, members (subscriptions),
 * invoices + dues, and reviews — in a single structured payload.
 */
class AdminWorkspaceService
{
    public function __construct(private readonly WorkspaceService $workspaces) {}

    /**
     * @return array<string, mixed>
     */
    public function detailFor(Workspace $workspace): array
    {
        $workspace->loadMissing(['owner:id,name,email,phone,avatar', 'seatTypes']);

        return [
            'id' => $workspace->id,
            'name' => $workspace->name,
            'description' => $workspace->description,
            'address' => $workspace->address,
            'city' => $workspace->city,
            'phone' => $workspace->phone,
            'status' => $workspace->status->value,
            'is_published' => $workspace->published_at !== null,
            'published_at' => $workspace->published_at?->toIso8601String(),
            'avg_rating' => $workspace->avg_rating,
            'amenities' => $workspace->amenities ?? [],
            'photos' => array_map(
                static fn (string $p): string => MediaUrl::resolve($p) ?? $p,
                $workspace->photos ?? [],
            ),
            'logo' => MediaUrl::resolve($workspace->owner?->avatar),
            'created_at' => $workspace->created_at?->toIso8601String(),
            'owner' => $workspace->owner === null ? null : [
                'id' => $workspace->owner->id,
                'name' => $workspace->owner->name,
                'email' => $workspace->owner->email,
                'phone' => $workspace->owner->phone,
                'avatar' => MediaUrl::resolve($workspace->owner->avatar),
            ],
            'seat_types' => $workspace->seatTypes->map(static fn ($row): array => [
                'type' => $row->type->value,
                'price_monthly' => $row->price_monthly,
                'price_daily' => $row->price_daily,
                'capacity' => $row->capacity,
                'enabled' => $row->enabled,
            ])->all(),
            'seats' => $this->workspaces->seatsSummary($workspace->id),
            'subscriptions' => $this->subscriptions($workspace),
            'invoices' => $this->invoices($workspace),
            'invoices_summary' => $this->invoicesSummary($workspace),
            'reviews' => $this->workspaces->recentReviews($workspace->id, 10),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function subscriptions(Workspace $workspace): array
    {
        return Subscription::query()
            ->where('workspace_id', $workspace->id)
            ->with(['member:id,name,email,avatar', 'seat:id,seat_number'])
            ->latest()
            ->limit(50)
            ->get()
            ->map(static fn (Subscription $s): array => [
                'id' => $s->id,
                'plan_type' => $s->plan_type->value,
                'monthly_price' => $s->monthly_price,
                'status' => $s->status->value,
                'start_date' => $s->start_date?->toDateString(),
                'end_date' => $s->end_date?->toDateString(),
                'seat_number' => $s->seat?->seat_number,
                'member' => $s->member === null ? null : [
                    'id' => $s->member->id,
                    'name' => $s->member->name,
                    'email' => $s->member->email,
                    'avatar' => MediaUrl::resolve($s->member->avatar),
                ],
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function invoices(Workspace $workspace): array
    {
        return $this->workspaceInvoices($workspace)
            ->latest('invoices.created_at')
            ->limit(50)
            ->get()
            ->map(static fn (Invoice $i): array => [
                'id' => $i->id,
                'invoice_number' => $i->invoice_number,
                'amount' => $i->amount,
                'status' => $i->status->value,
                'due_date' => $i->due_date?->toDateString(),
                'paid_at' => $i->paid_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function invoicesSummary(Workspace $workspace): array
    {
        $unpaidStatuses = [InvoiceStatus::Pending->value, InvoiceStatus::Overdue->value];

        return [
            'total_count' => $this->workspaceInvoices($workspace)->count(),
            'pending_count' => $this->workspaceInvoices($workspace)
                ->where('invoices.status', InvoiceStatus::Pending->value)->count(),
            'overdue_count' => $this->workspaceInvoices($workspace)
                ->where('invoices.status', InvoiceStatus::Overdue->value)->count(),
            'total_unpaid' => (string) $this->workspaceInvoices($workspace)
                ->whereIn('invoices.status', $unpaidStatuses)->sum('invoices.amount'),
            'total_overdue' => (string) $this->workspaceInvoices($workspace)
                ->where('invoices.status', InvoiceStatus::Overdue->value)->sum('invoices.amount'),
        ];
    }

    /**
     * Invoices belonging to this workspace, joined through subscriptions.
     *
     * @return Builder<Invoice>
     */
    private function workspaceInvoices(Workspace $workspace): Builder
    {
        return Invoice::query()
            ->join('subscriptions', 'subscriptions.id', '=', 'invoices.subscription_id')
            ->where('subscriptions.workspace_id', $workspace->id)
            ->select('invoices.*');
    }
}
