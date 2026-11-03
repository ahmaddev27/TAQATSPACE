<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The mode a router last reported in its sync header (`full` or `poll-only`).
 * Rendered scripts must carry it, otherwise an update would reset a poll-only
 * router to full mode.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routers', function (Blueprint $table): void {
            $table->string('mode', 20)->nullable()->after('script_version');
        });
    }

    public function down(): void
    {
        Schema::table('routers', function (Blueprint $table): void {
            $table->dropColumn('mode');
        });
    }
};
