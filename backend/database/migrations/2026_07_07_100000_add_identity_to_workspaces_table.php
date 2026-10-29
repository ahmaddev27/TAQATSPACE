<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

/**
 * Stable tenant identity (slug + code) and platform fields. Identity columns
 * start nullable so existing rows can be backfilled; the unique indexes allow
 * many NULLs, so they are safe before and after the backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table): void {
            $table->string('slug', 191)->nullable()->unique();
            $table->string('code', 8)->nullable()->unique();
            $table->string('platform_plan', 32)->default('standard');
            $table->string('platform_status', 32)->default('active')->index();
        });

        Artisan::call('workspaces:backfill-identity');
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table): void {
            $table->dropUnique(['slug']);
            $table->dropUnique(['code']);
            $table->dropIndex(['platform_status']);
            $table->dropColumn(['slug', 'code', 'platform_plan', 'platform_status']);
        });
    }
};
