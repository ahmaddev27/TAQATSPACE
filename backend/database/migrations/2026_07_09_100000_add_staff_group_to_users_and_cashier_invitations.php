<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workspace staff groups (cashier / hospitality / finance_supervisor). Null on
 * legacy rows, which behave as the `cashier` group.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cashier_invitations', function (Blueprint $table): void {
            $table->string('staff_group', 32)->nullable()->after('permissions');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('staff_group', 32)->nullable()->after('workspace_id');
            $table->index(['workspace_id', 'staff_group']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['workspace_id', 'staff_group']);
            $table->dropColumn('staff_group');
        });

        Schema::table('cashier_invitations', function (Blueprint $table): void {
            $table->dropColumn('staff_group');
        });
    }
};
