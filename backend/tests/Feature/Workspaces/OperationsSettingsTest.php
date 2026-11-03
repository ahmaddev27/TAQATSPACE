<?php

declare(strict_types=1);

namespace Tests\Feature\Workspaces;

use App\Enums\AdminPermission;
use App\Enums\AdminRole;
use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Models\ApprovalRequest;
use App\Models\SeatTypePrice;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\ApprovalRequestedNotification;
use App\Services\Approvals\ApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** SET-OPS / FLOW-5: operations settings are submitted for approval, never saved directly. */
class OperationsSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/workspace/settings/operations';

    private Workspace $workspace;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (AdminRole::assignable() as $role) {
            Role::findOrCreate($role, 'web');
        }
        foreach (AdminPermission::values() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        Notification::fake();

        $this->owner = User::factory()->owner()->create();
        $this->workspace = Workspace::factory()->create([
            'owner_id' => $this->owner->id,
            'working_hours' => ['saturday' => ['open' => true, 'from' => '09:00', 'to' => '18:00']],
        ]);
        SeatTypePrice::factory()->create([
            'workspace_id' => $this->workspace->id,
            'type' => 'flexible',
            'capacity' => 10,
            'price_monthly' => 100,
            'price_daily' => 10,
            'enabled' => true,
        ]);
        Sanctum::actingAs($this->owner);
    }

    private ?User $approver = null;

    private function approver(): User
    {
        if ($this->approver === null) {
            $this->approver = User::factory()->admin()->create();
            $this->approver->syncRoles([AdminRole::SuperAdmin->value]);
            $this->approver->syncPermissions(AdminPermission::values());
        }

        return $this->approver;
    }

    private function flexible(): SeatTypePrice
    {
        return $this->workspace->seatTypes()->where('type', 'flexible')->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function row(array $overrides = []): array
    {
        return array_merge(['type' => 'flexible', 'capacity' => 10, 'price_monthly' => 100, 'price_daily' => 10, 'enabled' => true], $overrides);
    }

    public function test_capacity_change_creates_a_pending_request_and_leaves_live_data(): void
    {
        $approver = $this->approver();
        $seatsBefore = $this->workspace->fresh()->total_seats;

        $this->putJson(self::URL, ['seat_types' => [$this->row(['capacity' => 25])]])
            ->assertStatus(202)
            ->assertJsonCount(1, 'data.requests')
            ->assertJsonPath('data.requests.0.type', 'capacity')
            ->assertJsonPath('data.requests.0.status', 'pending')
            ->assertJsonPath('data.requests.0.change.current.seat_types.0.capacity', 10)
            ->assertJsonPath('data.requests.0.change.proposed.seat_types.0.capacity', 25);

        $this->assertSame(10, $this->flexible()->capacity);
        $this->assertSame($seatsBefore, $this->workspace->fresh()->total_seats);
        Notification::assertSentTo($approver, ApprovalRequestedNotification::class);
    }

    public function test_approving_capacity_applies_seats_mode_and_audits_the_applied_values(): void
    {
        $this->putJson(self::URL, ['seat_types' => [$this->row(['capacity' => 25])], 'seating_mode' => 'fixed'])->assertStatus(202);
        $request = ApprovalRequest::query()->where('type', 'capacity')->firstOrFail();

        app(ApprovalService::class)->approve($request, $this->approver());

        $fresh = $this->workspace->fresh();
        $this->assertSame(25, $this->flexible()->capacity);
        $this->assertSame('100.00', $this->flexible()->price_monthly);
        $this->assertSame(25, $fresh->total_seats);
        $this->assertSame('fixed', $fresh->seating_mode->value);
        $this->assertDatabaseHas('audit_logs', ['action' => 'approval.approved', 'subject_id' => $request->id]);
        $log = \App\Models\AuditLog::query()->where('action', 'approval.approved')->firstOrFail();
        $this->assertSame(25, $log->changes['applied']['seat_types'][0]['capacity']);
    }

    public function test_rejecting_applies_nothing(): void
    {
        $this->putJson(self::URL, [
            'seat_types' => [$this->row(['capacity' => 99, 'price_monthly' => 500])],
            'tax_rate' => 15,
            'working_hours' => ['sunday' => ['open' => true, 'from' => '08:00', 'to' => '16:00']],
        ])->assertStatus(202)->assertJsonCount(3, 'data.requests');

        foreach (ApprovalRequest::query()->get() as $request) {
            app(ApprovalService::class)->reject($request, $this->approver(), 'No');
        }

        $fresh = $this->workspace->fresh();
        $this->assertSame(10, $this->flexible()->capacity);
        $this->assertSame('100.00', $this->flexible()->price_monthly);
        $this->assertSame('0.00', $fresh->tax_rate);
        $this->assertArrayHasKey('saturday', $fresh->working_hours);
        $this->assertSame(0, ApprovalRequest::query()->where('status', ApprovalStatus::Approved->value)->count());
    }

    public function test_approving_pricing_applies_prices_and_tax_without_touching_capacity(): void
    {
        $this->putJson(self::URL, ['seat_types' => [$this->row(['price_monthly' => 150, 'price_daily' => null])], 'tax_rate' => 16.5])
            ->assertStatus(202)
            ->assertJsonCount(1, 'data.requests')
            ->assertJsonPath('data.requests.0.type', 'pricing');
        $request = ApprovalRequest::query()->where('type', ApprovalType::Pricing->value)->firstOrFail();

        app(ApprovalService::class)->approve($request, $this->approver());

        $fresh = $this->workspace->fresh();
        $this->assertSame('150.00', $this->flexible()->price_monthly);
        $this->assertNull($this->flexible()->price_daily);
        $this->assertSame(10, $this->flexible()->capacity);
        $this->assertSame('16.50', $fresh->tax_rate);
        $this->assertSame('150.00', $fresh->price_per_month);
    }

    public function test_approving_working_hours_applies_them(): void
    {
        $hours = ['sunday' => ['open' => true, 'from' => '08:00', 'to' => '16:00']];
        $this->putJson(self::URL, ['working_hours' => $hours])->assertStatus(202)
            ->assertJsonPath('data.requests.0.type', 'working_hours');
        $this->assertArrayHasKey('saturday', $this->workspace->fresh()->working_hours);

        app(ApprovalService::class)->approve(ApprovalRequest::query()->firstOrFail(), $this->approver());

        $this->assertSame($hours, $this->workspace->fresh()->working_hours);
    }

    public function test_a_second_pending_request_of_the_same_type_replaces_the_first(): void
    {
        $this->putJson(self::URL, ['seat_types' => [$this->row(['capacity' => 20])]])->assertStatus(202);
        $this->putJson(self::URL, ['seat_types' => [$this->row(['capacity' => 30])]])->assertStatus(202);

        $this->assertSame(1, ApprovalRequest::query()->where('status', ApprovalStatus::Pending->value)->count());
        $this->assertSame(1, ApprovalRequest::query()->where('status', ApprovalStatus::Cancelled->value)->count());
        $pending = ApprovalRequest::query()->where('status', ApprovalStatus::Pending->value)->firstOrFail();
        $this->assertSame(30, $pending->payload['seat_types'][0]['capacity']);
    }

    public function test_an_unchanged_form_creates_nothing(): void
    {
        $this->putJson(self::URL, ['seat_types' => [$this->row()], 'tax_rate' => 0])
            ->assertOk()
            ->assertJsonCount(0, 'data.requests');

        $this->assertSame(0, ApprovalRequest::query()->count());
    }

    public function test_tax_rate_must_be_between_zero_and_one_hundred(): void
    {
        $this->putJson(self::URL, ['tax_rate' => 100.5])->assertUnprocessable()->assertJsonValidationErrors('tax_rate');
        $this->putJson(self::URL, ['tax_rate' => -1])->assertUnprocessable()->assertJsonValidationErrors('tax_rate');
        $this->putJson(self::URL, ['tax_rate' => 100])->assertStatus(202);
    }

    public function test_an_empty_submission_and_bad_values_are_rejected(): void
    {
        $this->putJson(self::URL, [])->assertUnprocessable();
        $this->putJson(self::URL, ['seating_mode' => 'hammock'])->assertUnprocessable()->assertJsonValidationErrors('seating_mode');
        $this->putJson(self::URL, ['seat_types' => [['type' => 'bogus', 'capacity' => 1]]])->assertUnprocessable();
        $this->putJson(self::URL, ['seat_types' => [$this->row(['capacity' => -3])]])->assertUnprocessable();
        $this->assertSame(0, ApprovalRequest::query()->count());
    }

    public function test_owner_can_cancel_the_pending_request(): void
    {
        $this->putJson(self::URL, ['tax_rate' => 5])->assertStatus(202);
        $request = ApprovalRequest::query()->firstOrFail();

        $this->postJson("/api/workspace/approvals/{$request->id}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertSame('0.00', $this->workspace->fresh()->tax_rate);
    }

    public function test_general_settings_endpoint_no_longer_saves_working_hours(): void
    {
        $this->putJson('/api/workspace/settings', ['name' => 'Renamed', 'working_hours' => ['sunday' => ['open' => true]]])->assertOk();

        $fresh = $this->workspace->fresh();
        $this->assertSame('Renamed', $fresh->name);
        $this->assertArrayHasKey('saturday', $fresh->working_hours);
        $this->assertArrayNotHasKey('sunday', $fresh->working_hours);
    }

    public function test_only_the_workspace_owner_can_submit(): void
    {
        $this->putJson(self::URL, ['tax_rate' => 5])->assertStatus(202);
        Sanctum::actingAs(User::factory()->create());
        $this->putJson(self::URL, ['tax_rate' => 7])->assertForbidden();

        Sanctum::actingAs(User::factory()->owner()->create());
        $this->putJson(self::URL, ['tax_rate' => 7])->assertNotFound();
    }

    public function test_public_payload_hides_the_tax_rate(): void
    {
        $this->workspace->update(['published_at' => now()]);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/workspaces/{$this->workspace->id}")->assertOk()->assertJsonMissingPath('data.tax_rate');
    }
}
