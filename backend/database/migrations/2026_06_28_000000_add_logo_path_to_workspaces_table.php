<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A dedicated workspace logo, distinct from the owner's personal avatar. When
 * null, readers fall back to the owner avatar so existing workspaces are
 * unaffected until they upload their own logo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table): void {
            $table->string('logo_path')->nullable()->after('photos');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table): void {
            $table->dropColumn('logo_path');
        });
    }
};
