<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Loyalty and strength scores per subscriber, derived from stats_monthly_subscriber
 * by ScoringService. Derived data: a recompute overwrites the row. A workspace may
 * override the default weights and thresholds (config/scoring.php) in `workspaces.scoring`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriber_scores', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('subscriber_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('loyalty_score', 5, 2)->default(0);
            $table->decimal('strength_score', 5, 2)->default(0);
            $table->string('segment', 20);
            $table->dateTime('computed_at');
            // The normalised components and raw figures behind both scores, so a score can be explained.
            $table->json('components')->nullable();

            $table->unique(['workspace_id', 'subscriber_id']);
            $table->index(['workspace_id', 'segment']);
        });

        Schema::table('workspaces', function (Blueprint $table): void {
            $table->json('scoring')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table): void {
            $table->dropColumn('scoring');
        });
        Schema::dropIfExists('subscriber_scores');
    }
};
