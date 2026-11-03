<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sensitive workspace changes wait here for a super admin's decision. Requester
 * and decider are plain UUID columns (no foreign keys) so the history outlives
 * deleted accounts; the workspace cascades because a request is meaningless
 * without it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->json('payload');
            $table->string('status', 16)->default('pending');
            $table->uuid('requested_by')->nullable();
            $table->uuid('decided_by')->nullable();
            $table->text('decision_reason')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'type']);
            $table->index(['workspace_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
    }
};
