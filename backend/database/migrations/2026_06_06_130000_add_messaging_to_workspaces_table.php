<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-workspace messaging configuration (SMTP + SMS). A workspace either uses
 * Taqat's platform accounts (`use_platform = true`, the default) or supplies its
 * own. Secret sub-values (smtp password, sms credentials) are encrypted at the
 * application layer before being written into this JSON column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table): void {
            $table->json('messaging')->nullable()->after('working_hours');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table): void {
            $table->dropColumn('messaging');
        });
    }
};
