<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\UnbilledChargeStatus;
use App\Exceptions\UnbilledChargeException;
use App\Models\UnbilledCharge;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Finance\UnbilledChargeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnbilledChargeServiceTest extends TestCase
{
    use RefreshDatabase;

    private UnbilledChargeService $service;

    private Workspace $workspace;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(UnbilledChargeService::class);
        $this->workspace = Workspace::factory()->create();
        $this->customer = User::factory()->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function data(array $override = []): array
    {
        return [...[
            'customer_id' => $this->customer->id,
            'description' => 'Extra printing',
            'qty' => '3',
            'unit_price' => '2.50',
            'source' => 'pos',
            'source_ref' => 'ORD-1',
        ], ...$override];
    }

    public function test_amount_is_computed_from_qty_and_unit_price(): void
    {
        $charge = $this->service->record($this->workspace, $this->data(['amount' => '999']));

        $this->assertSame('7.50', (string) $charge->amount);
        $this->assertSame(UnbilledChargeStatus::Open, $charge->status);
    }

    public function test_recording_the_same_source_ref_twice_keeps_one_charge(): void
    {
        $first = $this->service->record($this->workspace, $this->data());
        $second = $this->service->record($this->workspace, $this->data(['qty' => '9']));

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, UnbilledCharge::query()->count());
    }

    public function test_charges_without_a_source_ref_are_not_deduplicated(): void
    {
        $this->service->record($this->workspace, $this->data(['source_ref' => null]));
        $this->service->record($this->workspace, $this->data(['source_ref' => null]));

        $this->assertSame(2, UnbilledCharge::query()->count());
    }

    public function test_void_only_works_on_open_charges(): void
    {
        $actor = User::factory()->owner()->create();
        $charge = $this->service->record($this->workspace, $this->data());

        $this->assertSame(UnbilledChargeStatus::Voided, $this->service->void($charge, $actor)->status);

        $this->expectException(UnbilledChargeException::class);
        $this->service->void($charge, $actor);
    }

    public function test_mark_invoiced_links_the_line_and_blocks_voiding(): void
    {
        $actor = User::factory()->owner()->create();
        $charge = $this->service->record($this->workspace, $this->data());
        $lineId = (string) \Illuminate\Support\Str::uuid();

        $this->assertSame(1, $this->service->markInvoiced($this->workspace, [$charge->id], $lineId));
        $this->assertSame($lineId, $charge->fresh()->invoice_line_id);
        $this->assertSame(UnbilledChargeStatus::Invoiced, $charge->fresh()->status);

        $this->expectException(UnbilledChargeException::class);
        $this->service->void($charge->fresh(), $actor);
    }

    public function test_mark_invoiced_is_all_or_nothing(): void
    {
        $open = $this->service->record($this->workspace, $this->data());
        $voided = $this->service->record($this->workspace, $this->data(['source_ref' => 'ORD-2']));
        $this->service->void($voided, User::factory()->owner()->create());

        try {
            $this->service->markInvoiced($this->workspace, [$open->id, $voided->id], (string) \Illuminate\Support\Str::uuid());
            $this->fail('Expected the batch to be rejected.');
        } catch (UnbilledChargeException) {
            $this->assertSame(UnbilledChargeStatus::Open, $open->fresh()->status);
        }
    }

    public function test_list_open_for_customer_excludes_other_statuses_and_workspaces(): void
    {
        $open = $this->service->record($this->workspace, $this->data());
        UnbilledCharge::factory()->status(UnbilledChargeStatus::Voided)->create([
            'workspace_id' => $this->workspace->id,
            'customer_id' => $this->customer->id,
        ]);
        UnbilledCharge::factory()->create(['customer_id' => $this->customer->id]);

        $ids = $this->service->listOpenForCustomer($this->workspace, $this->customer->id)->pluck('id')->all();

        $this->assertSame([$open->id], $ids);
    }
}
