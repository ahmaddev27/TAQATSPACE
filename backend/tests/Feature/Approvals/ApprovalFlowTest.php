<?php

declare(strict_types=1);

namespace Tests\Feature\Approvals;

use App\Contracts\ApprovalHandler;
use App\Enums\AdminPermission;
use App\Enums\AdminRole;
use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\ApprovalDecidedNotification;
use App\Notifications\ApprovalRequestedNotification;
use App\Services\Approvals\ApprovalHandlerRegistry;
use App\Services\Approvals\ApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApprovalFlowTest extends TestCase
{
    use RefreshDatabase;

    private FakeApprovalHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (AdminRole::assignable() as $role) {
            Role::findOrCreate($role, 'web');
        }
        foreach (AdminPermission::values() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $this->handler = new FakeApprovalHandler;
        app(ApprovalHandlerRegistry::class)->register($this->handler);
        Notification::fake();
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->admin()->create();
        $admin->syncRoles([AdminRole::SuperAdmin->value]);
        $admin->syncPermissions(AdminPermission::values());

        return $admin;
    }

    private function adminWithout(): User
    {
        $admin = User::factory()->admin()->create();
        $admin->syncRoles([AdminRole::Admin->value]);
        $admin->syncPermissions(AdminPermission::defaultsForAdmin());

        return $admin;
    }

    /** @return array{0: User, 1: Workspace} */
    private function ownerWithWorkspace(): array
    {
        $owner = User::factory()->owner()->create();

        return [$owner, Workspace::factory()->create(['owner_id' => $owner->id, 'total_seats' => 10])];
    }

    private function submit(Workspace $workspace, User $owner, array $payload = ['total_seats' => 25]): ApprovalRequest
    {
        return app(ApprovalService::class)->submit($workspace, ApprovalType::Capacity, $payload, $owner);
    }

    public function test_submit_creates_a_pending_request_and_does_not_apply(): void
    {
        $admin = $this->superAdmin();
        [$owner, $workspace] = $this->ownerWithWorkspace();

        $request = $this->submit($workspace, $owner);

        $this->assertSame(ApprovalStatus::Pending, $request->status);
        $this->assertSame(0, $this->handler->applied);
        $this->assertSame(10, $workspace->fresh()->total_seats);
        Notification::assertSentTo($admin, ApprovalRequestedNotification::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'approval.requested', 'subject_id' => $request->id]);
    }

    public function test_submit_rejects_an_invalid_payload(): void
    {
        [$owner, $workspace] = $this->ownerWithWorkspace();
        $this->handler->failValidation = true;

        $this->expectException(ValidationException::class);
        $this->submit($workspace, $owner);
    }

    public function test_approve_applies_exactly_once_and_is_idempotent(): void
    {
        $admin = $this->superAdmin();
        [$owner, $workspace] = $this->ownerWithWorkspace();
        $request = $this->submit($workspace, $owner);
        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/approvals/{$request->id}/approve")
            ->assertOk()->assertJsonPath('data.status', 'approved');
        $this->postJson("/api/admin/approvals/{$request->id}/approve")->assertOk();

        $this->assertSame(1, $this->handler->applied);
        $this->assertDatabaseHas('approval_requests', [
            'id' => $request->id,
            'status' => 'approved',
            'decided_by' => $admin->id,
        ]);
        Notification::assertSentToTimes($owner, ApprovalDecidedNotification::class, 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'approval.approved', 'actor_id' => $admin->id]);
    }

    public function test_rejection_requires_a_reason_and_does_not_apply(): void
    {
        $admin = $this->superAdmin();
        [$owner, $workspace] = $this->ownerWithWorkspace();
        $request = $this->submit($workspace, $owner);
        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/approvals/{$request->id}/reject")
            ->assertUnprocessable()->assertJsonValidationErrors('reason');

        $this->postJson("/api/admin/approvals/{$request->id}/reject", ['reason' => 'Too many seats'])
            ->assertOk()->assertJsonPath('data.status', 'rejected');

        $this->assertSame(0, $this->handler->applied);
        $this->assertDatabaseHas('approval_requests', ['id' => $request->id, 'decision_reason' => 'Too many seats']);
        Notification::assertSentTo($owner, ApprovalDecidedNotification::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'approval.rejected', 'reason' => 'Too many seats']);
    }

    public function test_a_decided_request_cannot_be_decided_the_other_way(): void
    {
        $admin = $this->superAdmin();
        [$owner, $workspace] = $this->ownerWithWorkspace();
        $request = $this->submit($workspace, $owner);
        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/approvals/{$request->id}/approve")->assertOk();
        $this->postJson("/api/admin/approvals/{$request->id}/reject", ['reason' => 'changed my mind'])
            ->assertStatus(409);

        $this->assertSame(1, $this->handler->applied);
    }

    public function test_owner_can_cancel_a_pending_request_and_it_is_never_applied(): void
    {
        $admin = $this->superAdmin();
        [$owner, $workspace] = $this->ownerWithWorkspace();
        $request = $this->submit($workspace, $owner);

        Sanctum::actingAs($owner);
        $this->postJson("/api/workspace/approvals/{$request->id}/cancel")
            ->assertOk()->assertJsonPath('data.status', 'cancelled');

        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/approvals/{$request->id}/approve")->assertStatus(409);
        $this->assertSame(0, $this->handler->applied);
        $this->assertDatabaseHas('audit_logs', ['action' => 'approval.cancelled']);
    }

    public function test_admin_without_the_permission_gets_403(): void
    {
        [$owner, $workspace] = $this->ownerWithWorkspace();
        $request = $this->submit($workspace, $owner);
        Sanctum::actingAs($this->adminWithout());

        $this->getJson('/api/admin/approvals')->assertForbidden();
        $this->postJson("/api/admin/approvals/{$request->id}/approve")->assertForbidden();
        $this->postJson("/api/admin/approvals/{$request->id}/reject", ['reason' => 'nope'])->assertForbidden();
        $this->assertSame(0, $this->handler->applied);
    }

    public function test_owners_are_isolated_to_their_own_workspace(): void
    {
        [$owner, $workspace] = $this->ownerWithWorkspace();
        [$other, $otherWorkspace] = $this->ownerWithWorkspace();
        $mine = $this->submit($workspace, $owner);
        $theirs = $this->submit($otherWorkspace, $other);

        Sanctum::actingAs($owner);
        $this->getJson('/api/workspace/approvals')
            ->assertOk()
            ->assertJsonCount(1, 'data.requests')
            ->assertJsonPath('data.requests.0.id', $mine->id);
        $this->postJson("/api/workspace/approvals/{$theirs->id}/cancel")->assertNotFound();

        $this->assertSame(ApprovalStatus::Pending, $theirs->fresh()->status);
    }

    public function test_admin_list_spans_workspaces_filters_and_describes_the_change(): void
    {
        [$owner, $workspace] = $this->ownerWithWorkspace();
        [$other, $otherWorkspace] = $this->ownerWithWorkspace();
        $this->submit($workspace, $owner);
        $done = $this->submit($otherWorkspace, $other);
        $done->update(['status' => ApprovalStatus::Approved]);
        Sanctum::actingAs($this->superAdmin());

        $this->getJson('/api/admin/approvals')->assertOk()->assertJsonCount(2, 'data.requests');
        $this->getJson('/api/admin/approvals?status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data.requests')
            ->assertJsonPath('data.requests.0.change.proposed', 'fake-proposed')
            ->assertJsonPath('data.requests.0.workspace.id', $workspace->id);
        $this->getJson('/api/admin/approvals?status=bogus')->assertUnprocessable();
    }

    public function test_real_capacity_and_working_hours_handlers_apply_the_change(): void
    {
        $registry = new ApprovalHandlerRegistry([
            new \App\Services\Approvals\Handlers\CapacityApprovalHandler,
            new \App\Services\Approvals\Handlers\WorkingHoursApprovalHandler,
        ]);
        $this->app->instance(ApprovalHandlerRegistry::class, $registry);
        $admin = $this->superAdmin();
        [$owner, $workspace] = $this->ownerWithWorkspace();
        $service = app(ApprovalService::class);

        $capacity = $service->submit($workspace, ApprovalType::Capacity, ['total_seats' => 40], $owner);
        $hours = $service->submit($workspace, ApprovalType::WorkingHours, ['working_hours' => ['sat' => ['open' => '09:00', 'close' => '17:00']]], $owner);
        $service->approve($capacity, $admin);
        $service->approve($hours, $admin);

        $fresh = $workspace->fresh();
        $this->assertSame(40, $fresh->total_seats);
        $this->assertSame('17:00', $fresh->working_hours['sat']['close']);
    }
}

class FakeApprovalHandler implements ApprovalHandler
{
    public int $applied = 0;

    public bool $failValidation = false;

    public function type(): ApprovalType
    {
        return ApprovalType::Capacity;
    }

    public function validate(array $payload): array
    {
        if ($this->failValidation) {
            throw ValidationException::withMessages(['total_seats' => 'invalid']);
        }

        return $payload;
    }

    public function apply(ApprovalRequest $request): void
    {
        $this->applied++;
    }

    public function describe(ApprovalRequest $request): array
    {
        return ['current' => $request->workspace?->total_seats, 'proposed' => 'fake-proposed'];
    }
}
