<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\AdminPermission;
use App\Enums\AdminRole;
use App\Enums\WorkspaceFeature;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Workspace;
use App\Services\FeatureFlagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkspaceFeatureFlagTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (AdminRole::assignable() as $role) {
            Role::findOrCreate($role, 'web');
        }
        foreach (AdminPermission::values() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        Route::middleware(['auth:sanctum', 'feature:network'])
            ->get('/api/_test/network', static fn () => response()->json(['ok' => true]));
    }

    private function admin(array $permissions = []): User
    {
        $admin = User::factory()->admin()->create();
        $admin->syncRoles([AdminRole::Admin->value]);
        $admin->syncPermissions($permissions);

        return $admin;
    }

    /** @return array{0: User, 1: Workspace} */
    private function ownerWithWorkspace(): array
    {
        $owner = User::factory()->owner()->create();

        return [$owner, Workspace::factory()->create(['owner_id' => $owner->id])];
    }

    public function test_flags_default_to_disabled(): void
    {
        [, $workspace] = $this->ownerWithWorkspace();

        $flags = app(FeatureFlagService::class)->flagsFor($workspace->id);

        $this->assertSame(
            ['network' => false, 'finance_v2' => false, 'gsg' => false],
            $flags,
        );
    }

    public function test_middleware_returns_403_when_disabled_and_200_when_enabled(): void
    {
        [$owner, $workspace] = $this->ownerWithWorkspace();
        Sanctum::actingAs($owner);

        $this->getJson('/api/_test/network')->assertForbidden();

        app(FeatureFlagService::class)->update($workspace, ['network' => true]);

        $this->getJson('/api/_test/network')->assertOk();
    }

    public function test_middleware_blocks_a_user_without_a_workspace(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/_test/network')->assertForbidden();
    }

    public function test_flags_are_scoped_per_workspace(): void
    {
        [, $first] = $this->ownerWithWorkspace();
        [, $second] = $this->ownerWithWorkspace();
        $service = app(FeatureFlagService::class);

        $service->update($first, ['gsg' => true]);

        $this->assertTrue($service->isEnabled($first->id, WorkspaceFeature::Gsg));
        $this->assertFalse($service->isEnabled($second->id, WorkspaceFeature::Gsg));
    }

    public function test_reads_are_cached_and_invalidated_on_update(): void
    {
        [, $workspace] = $this->ownerWithWorkspace();
        $service = app(FeatureFlagService::class);
        $service->flagsFor($workspace->id);

        DB::enableQueryLog();
        $service->flagsFor($workspace->id);
        $service->isEnabled($workspace->id, WorkspaceFeature::Network);
        $this->assertCount(0, DB::getQueryLog());

        $service->update($workspace, ['network' => true]);
        $this->assertTrue($service->isEnabled($workspace->id, WorkspaceFeature::Network));
    }

    public function test_admin_can_toggle_features_and_the_change_is_audited(): void
    {
        [, $workspace] = $this->ownerWithWorkspace();
        Sanctum::actingAs($this->admin([AdminPermission::ManageWorkspaces->value]));

        $this->putJson("/api/admin/workspaces/{$workspace->id}/features", [
            'features' => ['network' => true, 'gsg' => false],
        ])
            ->assertOk()
            ->assertJsonPath('data.features.network', true)
            ->assertJsonPath('data.features.finance_v2', false);

        $log = AuditLog::query()->where('action', 'workspace.features.updated')->sole();
        $this->assertSame($workspace->id, $log->workspace_id);
        $this->assertSame(['network' => ['enabled' => true]], $log->changes);
    }

    public function test_unchanged_flags_write_no_audit_entry(): void
    {
        [, $workspace] = $this->ownerWithWorkspace();
        Sanctum::actingAs($this->admin([AdminPermission::ManageWorkspaces->value]));

        $this->putJson("/api/admin/workspaces/{$workspace->id}/features", [
            'features' => ['network' => false],
        ])->assertOk();

        $this->assertSame(0, AuditLog::query()->where('action', 'workspace.features.updated')->count());
    }

    public function test_validation_rejects_empty_and_non_boolean_payloads(): void
    {
        [, $workspace] = $this->ownerWithWorkspace();
        Sanctum::actingAs($this->admin([AdminPermission::ManageWorkspaces->value]));
        $url = "/api/admin/workspaces/{$workspace->id}/features";

        $this->putJson($url, [])->assertUnprocessable();
        $this->putJson($url, ['features' => ['network' => 'maybe']])->assertUnprocessable();
    }

    public function test_toggle_requires_the_manage_workspaces_permission(): void
    {
        [$owner, $workspace] = $this->ownerWithWorkspace();
        $url = "/api/admin/workspaces/{$workspace->id}/features";
        $payload = ['features' => ['network' => true]];

        $this->putJson($url, $payload)->assertUnauthorized();

        Sanctum::actingAs($owner);
        $this->putJson($url, $payload)->assertForbidden();

        Sanctum::actingAs($this->admin([AdminPermission::ViewReports->value]));
        $this->putJson($url, $payload)->assertForbidden();
    }

    public function test_me_exposes_the_owner_workspace_features(): void
    {
        [$owner, $workspace] = $this->ownerWithWorkspace();
        app(FeatureFlagService::class)->update($workspace, ['finance_v2' => true]);
        Sanctum::actingAs($owner);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.features.finance_v2', true)
            ->assertJsonPath('data.user.features.network', false);
    }

    public function test_admin_list_and_detail_expose_features_without_n_plus_one(): void
    {
        $workspaces = collect(range(1, 3))->map(fn () => $this->ownerWithWorkspace()[1]);
        app(FeatureFlagService::class)->update($workspaces[0], ['network' => true]);
        Sanctum::actingAs($this->admin([AdminPermission::ManageWorkspaces->value]));

        DB::enableQueryLog();
        $list = $this->getJson('/api/admin/workspaces')->assertOk();
        $flagQueries = collect(DB::getQueryLog())
            ->filter(fn (array $q): bool => str_contains($q['query'], 'workspace_features'))
            ->count();
        $this->assertSame(1, $flagQueries);

        $rows = collect($list->json('data.data'));
        $this->assertTrue($rows->firstWhere('id', $workspaces[0]->id)['features']['network']);
        $this->assertFalse($rows->firstWhere('id', $workspaces[1]->id)['features']['network']);

        $this->getJson("/api/admin/workspaces/{$workspaces[0]->id}")
            ->assertOk()
            ->assertJsonPath('data.features.network', true);
    }
}
