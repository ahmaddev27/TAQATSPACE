<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Enums\ExpenseCategory;
use App\Enums\InvoiceStatus;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Owner\OwnerExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

/**
 * Covers OwnerExportService CSV streaming — invoices, members and expenses
 * scoped to a single workspace, with a UTF-8 BOM and the documented columns.
 */
class OwnerExportServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): OwnerExportService
    {
        return app(OwnerExportService::class);
    }

    /** Run a streamed CSV response and capture its raw body. */
    private function capture(StreamedResponse $response): string
    {
        ob_start();
        $response->sendContent();

        return (string) ob_get_clean();
    }

    /**
     * Parse captured CSV into rows, stripping the leading UTF-8 BOM.
     *
     * @return array<int, array<int, string>>
     */
    private function rows(string $csv): array
    {
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv) ?? $csv;
        $rows = [];

        foreach (explode("\n", trim($csv)) as $line) {
            if ($line === '') {
                continue;
            }
            $rows[] = str_getcsv($line, ',', '"', '');
        }

        return $rows;
    }

    public function test_unknown_type_throws_an_unhandled_match(): void
    {
        // The whitelist lives on the controller; the service match has no default
        // arm, so an unknown type is an \UnhandledMatchError by design.
        $this->expectException(\UnhandledMatchError::class);
        $this->service()->stream('bogus', Workspace::factory()->create(), []);
    }

    public function test_invoices_export_emits_bom_header_and_scoped_rows(): void
    {
        $workspace = Workspace::factory()->create();
        $subscription = Subscription::factory()->create(['workspace_id' => $workspace->id]);

        $member = User::factory()->freelancer()->create(['name' => 'Lina Saleh']);
        $subscription->member()->associate($member)->save();

        Invoice::factory()->create([
            'subscription_id' => $subscription->id,
            'invoice_number' => 'TAQAT-2026-0001',
            'amount' => '120.00',
            'status' => InvoiceStatus::Pending->value,
        ]);

        // Another workspace's invoice must NOT appear.
        $otherSub = Subscription::factory()->create();
        Invoice::factory()->create(['subscription_id' => $otherSub->id, 'invoice_number' => 'TAQAT-2026-9999']);

        $csv = $this->capture($this->service()->stream('invoices', $workspace, []));

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);

        $rows = $this->rows($csv);
        $this->assertSame(
            ['invoice_number', 'member_name', 'amount', 'status', 'due_date', 'paid_at'],
            $rows[0],
        );
        $this->assertCount(2, $rows); // header + one scoped invoice
        $this->assertSame('TAQAT-2026-0001', $rows[1][0]);
        $this->assertSame('Lina Saleh', $rows[1][1]);
        $this->assertStringNotContainsString('TAQAT-2026-9999', $csv);
    }

    public function test_invoices_export_applies_the_status_filter(): void
    {
        $workspace = Workspace::factory()->create();
        $subscription = Subscription::factory()->create(['workspace_id' => $workspace->id]);

        Invoice::factory()->create([
            'subscription_id' => $subscription->id,
            'invoice_number' => 'PAID-1',
            'status' => InvoiceStatus::Paid->value,
            'paid_at' => now(),
        ]);
        Invoice::factory()->create([
            'subscription_id' => $subscription->id,
            'invoice_number' => 'PENDING-1',
            'status' => InvoiceStatus::Pending->value,
        ]);

        $csv = $this->capture($this->service()->stream('invoices', $workspace, ['status' => InvoiceStatus::Paid->value]));

        $this->assertStringContainsString('PAID-1', $csv);
        $this->assertStringNotContainsString('PENDING-1', $csv);
    }

    public function test_members_export_lists_one_row_per_member_with_columns(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->freelancer()->create(['name' => 'Omar Khalil', 'email' => 'omar@mail.ps']);
        Subscription::factory()->create([
            'workspace_id' => $workspace->id,
            'member_id' => $member->id,
        ]);

        $csv = $this->capture($this->service()->stream('members', $workspace, []));
        $rows = $this->rows($csv);

        $this->assertSame(
            ['member_name', 'email', 'phone', 'seat_number', 'plan_type', 'monthly_price', 'status', 'start_date'],
            $rows[0],
        );
        $this->assertCount(2, $rows);
        $this->assertSame('Omar Khalil', $rows[1][0]);
        $this->assertSame('omar@mail.ps', $rows[1][1]);
    }

    public function test_expenses_export_emits_columns_and_is_workspace_scoped(): void
    {
        $workspace = Workspace::factory()->create();
        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'title' => 'Office Rent',
            'category' => ExpenseCategory::cases()[0],
            'amount' => '500.00',
            'spent_on' => '2026-03-01',
        ]);
        Expense::factory()->create(['workspace_id' => Workspace::factory()->create()->id, 'title' => 'Other WS']);

        $csv = $this->capture($this->service()->stream('expenses', $workspace, []));
        $rows = $this->rows($csv);

        $this->assertSame(['spent_on', 'title', 'category', 'amount', 'notes'], $rows[0]);
        $this->assertCount(2, $rows);
        $this->assertSame('Office Rent', $rows[1][1]);
        $this->assertStringNotContainsString('Other WS', $csv);
    }

    public function test_export_with_no_data_emits_only_the_header(): void
    {
        $csv = $this->capture($this->service()->stream('invoices', Workspace::factory()->create(), []));

        $this->assertCount(1, $this->rows($csv));
    }
}
