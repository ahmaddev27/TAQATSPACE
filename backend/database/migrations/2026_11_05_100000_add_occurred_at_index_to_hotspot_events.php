<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The retention cleanup (network:prune) filters raw events by age across all
 * routers, which the (router_id, occurred_at) index cannot serve.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotspot_events', function (Blueprint $table): void {
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::table('hotspot_events', function (Blueprint $table): void {
            $table->dropIndex(['occurred_at']);
        });
    }
};
