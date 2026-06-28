<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Invoice numbers read in the workspace's own name (a derived prefix) rather than
 * the platform's, and stay sequential within that prefix.
 */
class InvoiceNumberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function raiseInvoice(Workspace $workspace): string
    {
        $member = User::factory()->freelancer()->create();
        Subscription::factory()->create([
            'workspace_id' => $workspace->id,
            'member_id' => $member->id,
            'status' => SubscriptionStatus::Active->value,
            'monthly_price' => 100,
        ]);

        return app(InvoiceService::class)->createManual($workspace, [
            'member_id' => $member->id,
            'amount' => 100,
            'due_date' => Carbon::today()->addDays(7)->toDateString(),
        ])->invoice_number;
    }

    public function test_latin_named_workspace_prefixes_the_invoice_number(): void
    {
        $workspace = Workspace::factory()->create(['name' => 'Bright Tech']);
        $year = Carbon::today()->year;

        $number = $this->raiseInvoice($workspace);

        // Non-alphanumerics are stripped, upper-cased, capped: "Bright Tech" -> "BRIGHT".
        $this->assertSame("BRIGHT-{$year}-0001", $number);
    }

    public function test_arabic_only_name_falls_back_to_a_stable_code(): void
    {
        $workspace = Workspace::factory()->create(['name' => 'مساحة طاقة']);

        $number = $this->raiseInvoice($workspace);

        // No Latin characters to derive from → a "WS"-prefixed code from the id.
        $this->assertStringStartsWith('WS', $number);
        $this->assertMatchesRegularExpression('/^WS[A-Z0-9]{0,4}-\d{4}-0001$/', $number);
    }

    public function test_numbers_increment_per_workspace(): void
    {
        $workspace = Workspace::factory()->create(['name' => 'Acme']);
        $year = Carbon::today()->year;

        $first = $this->raiseInvoice($workspace);
        $second = $this->raiseInvoice($workspace);

        $this->assertSame("ACME-{$year}-0001", $first);
        $this->assertSame("ACME-{$year}-0002", $second);
    }
}
