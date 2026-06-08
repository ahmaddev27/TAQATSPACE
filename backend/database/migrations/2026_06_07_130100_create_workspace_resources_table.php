<?php

declare(strict_types=1);

use App\Enums\ResourceStatus;
use App\Enums\ResourceType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner-managed physical/bookable resources for a single workspace (meeting
 * rooms, private offices, equipment, parking…) with a quantity and an
 * availability status — part of the mini workspace management module.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_resources', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default(ResourceType::Other->value);
            $table->unsignedInteger('quantity')->default(1);
            $table->string('status')->default(ResourceStatus::Available->value);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_resources');
    }
};
