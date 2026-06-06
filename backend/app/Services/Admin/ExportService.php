<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\WorkspaceStatus;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams the super-admin CSV exports for invoices, subscriptions, users and
 * workspaces.
 *
 * Each export reuses the SAME filtered query builder as the matching list
 * endpoint (delegated to the existing admin services so filter logic stays in
 * one place — DRY), then streams the FULL filtered set with `cursor()` so memory
 * stays flat regardless of row count. A UTF-8 BOM is prepended so Arabic member
 * and workspace names open correctly in Excel.
 */
class ExportService
{
    /** Whitelisted export types — anything else is a 404 at the controller. */
    public const TYPES = ['invoices', 'subscriptions', 'users', 'workspaces'];

    public function __construct(
        private readonly AdminInvoiceService $invoices,
        private readonly AdminSubscriptionService $subscriptions,
        private readonly AdminUserService $users,
    ) {}

    /**
     * Build the streamed CSV download for a whitelisted export type.
     *
     * @param  array<string, mixed>  $filters
     */
    public function stream(string $type, array $filters): StreamedResponse
    {
        [$query, $header, $row] = $this->definition($type, $filters);

        $filename = sprintf('taqat-%s-%s.csv', $type, now()->format('Y-m-d'));

        return response()->streamDownload(
            function () use ($query, $header, $row): void {
                $handle = fopen('php://output', 'wb');

                // BOM so Excel detects UTF-8 and renders Arabic correctly.
                fwrite($handle, "\xEF\xBB\xBF");

                // Empty $escape (the PHP 8.4 default) gives RFC-4180 output.
                fputcsv($handle, $header, ',', '"', '');

                foreach ($query->cursor() as $model) {
                    fputcsv($handle, $row($model), ',', '"', '');
                }

                fclose($handle);
            },
            $filename,
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    /**
     * Resolve a type to its [query builder, header row, row mapper] triple.
     *
     * @param  array<string, mixed>  $filters
     * @return array{0: Builder<\Illuminate\Database\Eloquent\Model>, 1: array<int, string>, 2: callable}
     */
    private function definition(string $type, array $filters): array
    {
        return match ($type) {
            'invoices' => [
                $this->invoices->exportQuery($this->only($filters, ['status', 'search'])),
                ['invoice_number', 'member_name', 'workspace_name', 'amount', 'status', 'due_date', 'paid_at'],
                fn (Invoice $invoice): array => [
                    $invoice->invoice_number,
                    $invoice->subscription?->member?->name,
                    $invoice->subscription?->workspace?->name,
                    $invoice->amount,
                    $invoice->status->value,
                    $invoice->due_date?->toDateString(),
                    $invoice->paid_at?->toDateTimeString(),
                ],
            ],
            'subscriptions' => [
                // The list filter keys this builder on `member`; the export
                // contract calls it `search`, so map it across.
                $this->subscriptions->exportQuery($this->mapSubscriptionFilters($filters)),
                ['member_name', 'workspace_name', 'plan_type', 'monthly_price', 'start_date', 'end_date', 'status'],
                fn (Subscription $subscription): array => [
                    $subscription->member?->name,
                    $subscription->workspace?->name,
                    $subscription->plan_type->value,
                    $subscription->monthly_price,
                    $subscription->start_date?->toDateString(),
                    $subscription->end_date?->toDateString(),
                    $subscription->status->value,
                ],
            ],
            'users' => [
                $this->users->exportQuery($this->only($filters, ['role', 'status', 'search'])),
                ['name', 'email', 'phone', 'role', 'status', 'created_at'],
                fn (User $user): array => [
                    $user->name,
                    $user->email,
                    $user->phone,
                    $user->role->value,
                    $user->status->value,
                    $user->created_at?->toDateTimeString(),
                ],
            ],
            'workspaces' => [
                $this->workspaceQuery($filters),
                ['name', 'owner_name', 'city', 'status', 'total_seats', 'price_per_month', 'created_at'],
                fn (Workspace $workspace): array => [
                    $workspace->name,
                    $workspace->owner?->name,
                    $workspace->city,
                    $workspace->status->value,
                    $workspace->total_seats,
                    $workspace->price_per_month,
                    $workspace->created_at?->toDateTimeString(),
                ],
            ],
        };
    }

    /**
     * Workspaces have no admin list service, so build the (status-filtered)
     * export query here with the owner eager-loaded to avoid N+1 in the cursor.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Workspace>
     */
    private function workspaceQuery(array $filters): Builder
    {
        $query = Workspace::query()
            ->with('owner:id,name')
            ->latest();

        if (! empty($filters['status']) && WorkspaceStatus::tryFrom((string) $filters['status']) !== null) {
            $query->where('status', $filters['status']);
        }

        return $query;
    }

    /**
     * Translate the export's `search` param to the subscription list's `member`
     * key while preserving the `status` and `workspace_id` filters.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function mapSubscriptionFilters(array $filters): array
    {
        $mapped = $this->only($filters, ['status', 'workspace_id']);

        if (! empty($filters['search'])) {
            $mapped['member'] = $filters['search'];
        }

        return $mapped;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    private function only(array $filters, array $keys): array
    {
        return array_intersect_key($filters, array_flip($keys));
    }
}
