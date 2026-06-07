<?php

declare(strict_types=1);

namespace App\Services\Owner;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Workspace;
use App\Services\ExpenseService;
use App\Services\InvoiceService;
use App\Services\MemberService;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams the owner-facing CSV exports for the authenticated owner's OWN
 * workspace only — invoices and the member roster.
 *
 * Every query is scoped to the passed-in workspace and reuses the SAME filtered
 * query builders as the matching owner list endpoints (delegated to the shared
 * invoice/member services so filter logic stays in one place — DRY). The full
 * filtered set streams with `cursor()` so memory stays flat regardless of row
 * count. A UTF-8 BOM is prepended so Arabic names open correctly in Excel.
 */
class OwnerExportService
{
    /** Whitelisted owner export types — anything else is a 404 at the controller. */
    public const TYPES = ['invoices', 'members', 'expenses'];

    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly MemberService $members,
        private readonly ExpenseService $expenses,
    ) {}

    /**
     * Build the streamed CSV download for a whitelisted owner export type,
     * scoped to the given workspace.
     *
     * @param  array<string, mixed>  $filters
     */
    public function stream(string $type, Workspace $workspace, array $filters): StreamedResponse
    {
        [$query, $header, $row] = $this->definition($type, $workspace, $filters);

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
    private function definition(string $type, Workspace $workspace, array $filters): array
    {
        return match ($type) {
            'invoices' => [
                $this->invoices->exportQueryForWorkspace(
                    $workspace,
                    $this->only($filters, ['status', 'month']),
                ),
                ['invoice_number', 'member_name', 'amount', 'status', 'due_date', 'paid_at'],
                fn (Invoice $invoice): array => [
                    $invoice->invoice_number,
                    $invoice->subscription?->member?->name,
                    $invoice->amount,
                    $invoice->status->value,
                    $invoice->due_date?->toDateString(),
                    $invoice->paid_at?->toDateTimeString(),
                ],
            ],
            'members' => [
                $this->members->exportQueryForWorkspace(
                    $workspace,
                    $this->only($filters, ['status', 'search', 'sort', 'direction']),
                ),
                ['member_name', 'email', 'phone', 'seat_number', 'plan_type', 'monthly_price', 'status', 'start_date'],
                fn (Subscription $subscription): array => [
                    $subscription->member?->name,
                    $subscription->member?->email,
                    $subscription->member?->phone,
                    $subscription->seat?->seat_number,
                    $subscription->plan_type->value,
                    $subscription->monthly_price,
                    $subscription->status->value,
                    $subscription->start_date?->toDateString(),
                ],
            ],
            'expenses' => [
                $this->expenses->exportQueryForWorkspace(
                    $workspace,
                    $this->only($filters, ['category', 'from', 'to']),
                ),
                ['spent_on', 'title', 'category', 'amount', 'notes'],
                fn (Expense $expense): array => [
                    $expense->spent_on?->toDateString(),
                    $expense->title,
                    $expense->category->value,
                    $expense->amount,
                    $expense->notes,
                ],
            ],
        };
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
