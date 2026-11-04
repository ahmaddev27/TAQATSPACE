<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\Workspace;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Runs the branches migration itself. Schema changes commit implicitly on
 * MariaDB, so the usual wrapping transaction cannot undo these tests: each one
 * forces a fresh database for the next test, otherwise its rows would leak into
 * it. Other tables reference branches, hence the disabled foreign key checks
 * around the drop.
 */
class BranchesMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;

        parent::tearDown();
    }

    private function rerunMigration(?callable $beforeUp = null): void
    {
        $migration = require database_path('migrations/2026_07_08_100000_create_branches_table.php');

        Schema::withoutForeignKeyConstraints(function () use ($migration, $beforeUp): void {
            $migration->down();

            if ($beforeUp !== null) {
                $beforeUp();
            }

            $migration->up();
        });
    }

    public function test_migration_recovers_from_the_table_a_failed_run_left_behind(): void
    {
        $workspace = Workspace::factory()->create();

        $this->rerunMigration(function (): void {
            Schema::create('branches', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->boolean('is_default')->default(false);
            });
        });

        $default = DB::table('branches')->where('workspace_id', $workspace->id)->where('is_default', true)->sole();
        $this->assertSame($workspace->id, $default->default_workspace_id);
    }

    public function test_backfill_gives_every_existing_workspace_exactly_one_default_branch(): void
    {
        $workspaces = Workspace::factory()->count(3)->create();

        $this->rerunMigration();

        foreach ($workspaces as $workspace) {
            $count = DB::table('branches')->where('workspace_id', $workspace->id)->where('is_default', true)->count();
            $this->assertSame(1, $count);
        }
    }
}
