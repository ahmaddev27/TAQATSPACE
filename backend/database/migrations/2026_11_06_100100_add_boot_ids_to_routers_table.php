<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `boot_id` is the boot the router last reported; `sessions_boot_id` is the
 * boot its sessions were last built under. A difference means a restart.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routers', function (Blueprint $table): void {
            $table->string('boot_id', 32)->nullable();
            $table->string('sessions_boot_id', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('routers', function (Blueprint $table): void {
            $table->dropColumn(['boot_id', 'sessions_boot_id']);
        });
    }
};
