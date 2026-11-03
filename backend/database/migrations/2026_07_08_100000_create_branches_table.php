<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Branches of a workspace. "At most one default per workspace" is enforced by a
 * unique index on `default_workspace_id`, which holds the workspace id on the
 * default row and NULL on the others (the Branch model keeps it in step). It is
 * a plain column because MariaDB rejects this as a generated column and MySQL
 * has no partial index. Every existing workspace gets its default branch.
 */
return new class extends Migration
{
    public function up(): void
    {
        // An earlier version of this migration failed on MariaDB after creating the
        // table and was not recorded as run, so a table found here is an empty leftover.
        Schema::dropIfExists('branches');

        Schema::create('branches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('address')->nullable();
            $table->boolean('is_default')->default(false);
            $table->uuid('default_workspace_id')->nullable()->unique('branches_single_default_unique');
            $table->timestamps();
        });

        $this->backfillDefaultBranches();
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
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
                'default_workspace_id' => $workspace->id,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        });
    }
};
