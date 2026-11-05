<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per uploaded import file. `(workspace_id, file_hash)` is unique so the
 * same file can never be committed twice; the parsed rows wait on disk
 * (`payload_path`) between the preview and the commit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->char('file_hash', 64);
            $table->string('status', 20)->default('previewed');
            $table->unsignedInteger('row_count')->default(0);
            $table->json('errors')->nullable();
            $table->string('payload_path')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('committed_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'file_hash']);
            $table->index(['workspace_id', 'type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
