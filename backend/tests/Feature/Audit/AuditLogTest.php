<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Audit\AuditLogger;
use App\Services\Pos\CashierManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Workspace} */
    private function ownerWithWorkspace(): array
    {
        $owner = User::factory()->owner()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        return [$owner, $workspace];
    }

    private function entry(Workspace $workspace, string $action, array $attributes = []): AuditLog
    {
        return AuditLog::query()->create([
            'workspace_id' => $workspace->id,
            'actor_type' => 'system',
            'action' => $action,
            ...$attributes,
        ]);
    }

    public function test_logger_records_actor_ip_and_request_id_from_the_request(): void
    {
        [$owner, $workspace] = $this->ownerWithWorkspace();
        Sanctum::actingAs($owner);

        $response = $this->withHeader('X-Request-Id', 'req-abc-12345')
            ->getJson('/api/workspace/audit-logs');
        $response->assertOk()->assertHeader('X-Request-Id', 'req-abc-12345');

        request()->attributes->set('request_id', 'req-abc-12345');
        request()->setUserResolver(fn () => $owner);
        app(AuditLogger::class)->log('settings.updated', $workspace, 'because');

        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => $workspace->id,
            'actor_type' => 'user',
            'actor_id' => $owner->id,
            'action' => 'settings.updated',
            'reason' => 'because',
            'request_id' => 'req-abc-12345',
        ]);
    }

    public function test_owner_only_sees_their_own_workspace_log(): void
    {
        [$owner, $workspace] = $this->ownerWithWorkspace();
        [, $other] = $this->ownerWithWorkspace();
        $this->entry($workspace, 'mine.one');
        $this->entry($other, 'theirs.one');
        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/workspace/audit-logs?workspace_id='.$other->id);

        $response->assertOk()->assertJsonCount(1, 'data.logs')
            ->assertJsonPath('data.logs.0.action', 'mine.one');
    }

    public function test_filters_by_action_prefix_actor_and_period(): void
    {
        [$owner, $workspace] = $this->ownerWithWorkspace();
        $this->entry($workspace, 'finance.invoice.issued', ['actor_type' => 'user', 'actor_id' => $owner->id, 'reason' => 'monthly run']);
        $this->entry($workspace, 'permissions.changed');
        $old = $this->entry($workspace, 'finance.invoice.voided');
        DB::table('audit_logs')->where('id', $old->id)->update(['created_at' => '2020-01-01 10:00:00']);
        Sanctum::actingAs($owner);

        $this->getJson('/api/workspace/audit-logs?action=finance.*')
            ->assertJsonPath('data.meta.total', 2);
        $this->getJson('/api/workspace/audit-logs?action=permissions.changed')
            ->assertJsonPath('data.meta.total', 1);
        $this->getJson('/api/workspace/audit-logs?actor_id='.$owner->id)
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.logs.0.actor.id', $owner->id);
        $this->getJson('/api/workspace/audit-logs?from=2020-01-01&to=2020-01-02')
            ->assertJsonPath('data.meta.total', 1);
        $this->getJson('/api/workspace/audit-logs?reason=monthly')
            ->assertJsonPath('data.meta.total', 1);
    }

    public function test_results_are_paginated_without_n_plus_one(): void
    {
        [$owner, $workspace] = $this->ownerWithWorkspace();
        $actors = User::factory()->count(5)->create();
        foreach ($actors as $actor) {
            $this->entry($workspace, 'x.y', ['actor_type' => 'user', 'actor_id' => $actor->id]);
        }
        Sanctum::actingAs($owner);

        DB::enableQueryLog();
        $this->getJson('/api/workspace/audit-logs?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data.logs')
            ->assertJsonPath('data.meta.last_page', 3);
        $actorQueries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'from "users" where "users"."id" in'));

        $this->assertCount(1, $actorQueries);
    }

    public function test_entries_are_append_only(): void
    {
        [, $workspace] = $this->ownerWithWorkspace();
        $log = $this->entry($workspace, 'a.b');

        try {
            $log->update(['action' => 'changed']);
            $this->fail('Update must be refused.');
        } catch (LogicException) {
            $this->assertSame('a.b', $log->fresh()->action);
        }

        $this->expectException(LogicException::class);
        $log->delete();
    }

    public function test_no_write_routes_exist(): void
    {
        [$owner] = $this->ownerWithWorkspace();
        Sanctum::actingAs($owner);

        $this->assertContains($this->putJson('/api/workspace/audit-logs/1')->getStatusCode(), [404, 405]);
        $this->assertContains($this->deleteJson('/api/workspace/audit-logs/1')->getStatusCode(), [404, 405]);
        $this->assertSame(405, $this->postJson('/api/workspace/audit-logs')->getStatusCode());
    }

    public function test_endpoints_require_the_right_role(): void
    {
        $this->getJson('/api/workspace/audit-logs')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/workspace/audit-logs')->assertForbidden();
        $this->getJson('/api/admin/audit-logs')->assertForbidden();
    }

    public function test_inviting_staff_writes_an_audit_entry(): void
    {
        Notification::fake();
        [$owner, $workspace] = $this->ownerWithWorkspace();
        Sanctum::actingAs($owner);

        app(CashierManagementService::class)->invite($workspace, $owner, ['email' => 'new@example.com']);

        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => $workspace->id,
            'action' => 'staff.invited',
            'actor_id' => $owner->id,
        ]);
    }
}
