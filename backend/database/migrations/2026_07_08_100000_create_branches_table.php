<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Branches of a workspace. "At most one default per workspace" is enforced by a
 * unique index that only covers default rows: a partial index where the engine
 * supports it, otherwise (MySQL) a unique virtual column that is NULL for
 * non-default rows. Every existing workspace gets its default branch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('address')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        $this->addSingleDefaultIndex();
        $this->backfillDefaultBranches();
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }

    private function addSingleDefaultIndex(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE branches ADD default_workspace_id CHAR(36) AS (IF(is_default, workspace_id, NULL)) VIRTUAL, ADD UNIQUE branches_single_default_unique (default_workspace_id)');

            return;
        }

        DB::statement('CREATE UNIQUE INDEX branches_single_default_unique ON branches (workspace_id) WHERE is_default = 1');
    }

    private function backfillDefaultBranches(): void
    {
        DB::table('workspaces')->orderBy('id')->chunk(500, function ($workspaces): void {
            $now = now();

            DB::table('branches')->insert($workspaces->map(fn (object $workspace): array => [
                'id' => (string) Str::uuid(),
                'workspace_id' => $workspace->id,
                'name' => $workspace->name,
                'address' => $workspace->address,
                'is_default' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        });
    }
};
