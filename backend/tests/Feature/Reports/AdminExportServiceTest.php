<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Enums\InvoiceStatus;
use App\Enums\WorkspaceStatus;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Admin\ExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

/**
 * Covers Admin ExportService CSV streaming across the four whitelisted types —
 * invoices, subscriptions, users and workspaces — including the BOM, columns
 * and the status filter.
 */
class AdminExportServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ExportService
    {
        return app(ExportService::class);
    }

    private function capture(StreamedResponse $response): string
    {
        ob_start();
        $response->sendContent();

        return (string) ob_get_clean();
    }

    /**
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
        $this->expectException(\UnhandledMatchError::class);
        $this->service()->stream('bogus', []);
    }

    public function test_invoices_export_has_bom_columns_and_workspace_name(): void
    {
        $workspace = Workspace::factory()->create(['name' => 'Hub One']);
        $subscription = Subscription::factory()->create(['workspace_id' => $workspace->id]);
        $member = User::factory()->freelancer()->create(['name' => 'Sara N.']);
        $subscription->member()->associate($member)->save();

        Invoice::factory()->create([
            'subscription_id' => $subscription->id,
            'invoice_number' => 'ADM-INV-1',
            'amount' => '90.00',
            'status' => InvoiceStatus::Pending->value,
        ]);

        $csv = $this->capture($this->service()->stream('invoices', []));

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);

        $rows = $this->rows($csv);
        $this->assertSame(
            ['invoice_number', 'member_name', 'workspace_name', 'amount', 'status', 'due_date', 'paid_at'],
            $rows[0],
        );
        $this->assertSame('ADM-INV-1', $rows[1][0]);
        $this->assertSame('Sara N.', $rows[1][1]);
        $this->assertSame('Hub One', $rows[1][2]);
    }

    public function test_invoices_export_applies_status_filter(): void
    {
        Invoice::factory()->create(['invoice_number' => 'PAID-A', 'status' => InvoiceStatus::Paid->value, 'paid_at' => now()]);
        Invoice::factory()->create(['invoice_number' => 'PENDING-A', 'status' => InvoiceStatus::Pending->value]);

        $csv = $this->capture($this->service()->stream('invoices', ['status' => InvoiceStatus::Paid->value]));

        $this->assertStringContainsString('PAID-A', $csv);
        $this->assertStringNotContainsString('PENDING-A', $csv);
    }

    public function test_subscriptions_export_columns_and_rows(): void
    {
        $workspace = Workspace::factory()->create(['name' => 'Co Space']);
        $member = User::factory()->freelancer()->create(['name' => 'Tariq M.']);
        Subscription::factory()->create([
            'workspace_id' => $workspace->id,
            'member_id' => $member->id,
        ]);

        $csv = $this->capture($this->service()->stream('subscriptions', []));
        $rows = $this->rows($csv);

        $this->assertSame(
            ['member_name', 'workspace_name', 'plan_type', 'monthly_price', 'start_date', 'end_date', 'status'],
            $rows[0],
        );
        $this->assertSame('Tariq M.', $rows[1][0]);
        $this->assertSame('Co Space', $rows[1][1]);
    }

    public function test_users_export_columns_and_role_filter(): void
    {
        User::factory()->freelancer()->create(['name' => 'Freela', 'email' => 'f@mail.ps']);
        User::factory()->owner()->create(['name' => 'Owna']);

        $csv = $this->capture($this->service()->stream('users', ['role' => 'freelancer']));
        $rows = $this->rows($csv);

        $this->assertSame(['name', 'email', 'phone', 'role', 'status', 'created_at'], $rows[0]);
        $this->assertStringContainsString('Freela', $csv);
        $this->assertStringNotContainsString('Owna', $csv);
    }

    public function test_workspaces_export_columns_owner_name_and_status_filter(): void
    {
        $owner = User::factory()->owner()->create(['name' => 'Big Owner']);
        Workspace::factory()->create([
            'owner_id' => $owner->id,
            'name' => 'Active WS',
            'status' => WorkspaceStatus::Active->value,
        ]);
        Workspace::factory()->pending()->create(['name' => 'Pending WS']);

        $csv = $this->capture($this->service()->stream('workspaces', ['status' => WorkspaceStatus::Active->value]));
        $rows = $this->rows($csv);

        $this->assertSame(
            ['name', 'owner_name', 'city', 'status', 'total_seats', 'price_per_month', 'created_at'],
            $rows[0],
        );
        $this->assertStringContainsString('Active WS', $csv);
        $this->assertStringContainsString('Big Owner', $csv);
        $this->assertStringNotContainsString('Pending WS', $csv);
    }

    public function test_export_with_no_data_emits_only_header(): void
    {
        $csv = $this->capture($this->service()->stream('users', []));

        $this->assertCount(1, $this->rows($csv));
    }
}
