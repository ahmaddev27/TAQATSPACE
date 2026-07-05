<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Binds a cashier account to the single workspace it operates the POS for.
 *
 * Owners link to their workspace via `workspaces.owner_id` (a reverse HasOne),
 * but a cashier does not own the workspace — it needs its own forward pointer.
 * Nullable because every non-cashier account (freelancer, owner, admin) leaves
 * it empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignUuid('workspace_id')
                ->nullable()
                ->after('role')
                ->constrained('workspaces')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('workspace_id');
        });
    }
};
