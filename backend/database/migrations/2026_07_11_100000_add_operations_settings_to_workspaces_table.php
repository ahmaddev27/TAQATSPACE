<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Operations settings (SET-OPS): how seats are assigned and the tax percentage. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table): void {
            $table->string('seating_mode', 16)->default('flexible');
            $table->decimal('tax_rate', 5, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table): void {
            $table->dropColumn(['seating_mode', 'tax_rate']);
        });
    }
};
