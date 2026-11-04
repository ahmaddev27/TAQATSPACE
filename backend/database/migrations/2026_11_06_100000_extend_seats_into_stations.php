<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seats become stations: kind, zone, capacity, privacy, parent station and
 * branch. Every new column is nullable or defaulted so existing rows keep
 * working, and existing seats are attached to their workspace's default branch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seats', function (Blueprint $table): void {
            $table->string('zone', 100)->nullable();
            $table->string('kind', 30)->default('desk');
            $table->unsignedSmallInteger('capacity')->default(1);
            $table->boolean('is_private')->default(false);
            $table->foreignUuid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignUuid('parent_id')->nullable()->constrained('seats')->nullOnDelete();

            $table->index(['workspace_id', 'branch_id', 'zone'], 'seats_workspace_branch_zone_index');
        });

        $this->backfillDefaultBranch();
    }

    public function down(): void
    {
        Schema::table('seats', function (Blueprint $table): void {
            $table->dropIndex('seats_workspace_branch_zone_index');
            $table->dropConstrainedForeignId('parent_id');
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn(['zone', 'kind', 'capacity', 'is_private']);
        });
    }

    private function backfillDefaultBranch(): void
    {
        DB::table('seats')->whereNull('branch_id')->update([
            'branch_id' => DB::raw(
                '(select b.id from branches b where b.default_workspace_id = seats.workspace_id)',
            ),
        ]);
    }
};
