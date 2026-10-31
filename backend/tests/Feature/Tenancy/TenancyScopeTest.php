<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Tenancy\CurrentWorkspaceContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenancyScopeTest extends TestCase
{
    use RefreshDatabase;

    private function actAs(Workspace $workspace): void
    {
        app(CurrentWorkspaceContext::class)->set($workspace);
    }

    public function test_trait_fills_workspace_id_from_the_context(): void
    {
        $workspace = Workspace::factory()->create();
        $this->actAs($workspace);

        $branch = Branch::create(['name' => 'Annex']);

        $this->assertSame($workspace->id, $branch->workspace_id);
    }

    public function test_explicit_workspace_id_is_kept(): void
    {
        $a = Workspace::factory()->create();
        $b = Workspace::factory()->create();
        $this->actAs($a);

        $branch = Branch::create(['name' => 'Other', 'workspace_id' => $b->id]);

        $this->assertSame($b->id, $branch->workspace_id);
    }

    public function test_queries_only_return_rows_of_the_current_workspace(): void
    {
        $a = Workspace::factory()->create();
        $b = Workspace::factory()->create();
        Branch::factory()->create(['workspace_id' => $a->id, 'name' => 'A2']);
        Branch::factory()->create(['workspace_id' => $b->id, 'name' => 'B2']);

        $this->actAs($a);

        $names = Branch::query()->pluck('name')->all();

        $this->assertContains('A2', $names);
        $this->assertNotContains('B2', $names);
        $this->assertSame([$a->id], Branch::query()->pluck('workspace_id')->unique()->values()->all());
    }

    public function test_without_workspace_scope_returns_every_row(): void
    {
        $a = Workspace::factory()->create();
        $b = Workspace::factory()->create();
        $this->actAs($a);

        $all = Branch::withoutWorkspaceScope()->pluck('workspace_id')->unique();

        $this->assertEqualsCanonicalizing([$a->id, $b->id], $all->all());
    }

    public function test_no_context_returns_nothing(): void
    {
        Workspace::factory()->create();

        $this->assertSame(0, Branch::query()->count());
        $this->assertGreaterThan(0, Branch::withoutWorkspaceScope()->count());
    }

    public function test_every_new_workspace_gets_one_default_branch(): void
    {
        $workspace = Workspace::factory()->create(['name' => 'Hub']);

        $defaults = Branch::withoutWorkspaceScope()->where('workspace_id', $workspace->id)->where('is_default', true)->get();

        $this->assertCount(1, $defaults);
        $this->assertSame('Hub', $defaults->first()->name);
    }

    public function test_a_workspace_cannot_have_two_default_branches(): void
    {
        $workspace = Workspace::factory()->create();

        $this->expectException(QueryException::class);

        Branch::factory()->default()->create(['workspace_id' => $workspace->id]);
    }

    public function test_backfill_gives_every_existing_workspace_exactly_one_default_branch(): void
    {
        $workspaces = Workspace::factory()->count(3)->create();
        $migration = require database_path('migrations/2026_07_08_100000_create_branches_table.php');
        $migration->down();
        $migration->up();

        foreach ($workspaces as $workspace) {
            $count = DB::table('branches')->where('workspace_id', $workspace->id)->where('is_default', true)->count();
            $this->assertSame(1, $count);
        }
    }

    public function test_middleware_binds_the_owner_workspace(): void
    {
        $owner = User::factory()->owner()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        Route::middleware(['api', 'auth:sanctum', 'workspace.current'])
            ->get('/api/_tenancy-probe', fn () => ['id' => app(CurrentWorkspaceContext::class)->id()]);
        Sanctum::actingAs($owner);

        $this->getJson('/api/_tenancy-probe')->assertOk()->assertJson(['id' => $workspace->id]);
    }

    public function test_middleware_rejects_a_user_without_workspace_context(): void
    {
        $freelancer = User::factory()->create(['role' => UserRole::Freelancer]);
        Route::middleware(['api', 'auth:sanctum', 'workspace.current'])
            ->get('/api/_tenancy-probe', fn () => ['ok' => true]);
        Sanctum::actingAs($freelancer);

        $this->getJson('/api/_tenancy-probe')->assertForbidden();
    }
}
