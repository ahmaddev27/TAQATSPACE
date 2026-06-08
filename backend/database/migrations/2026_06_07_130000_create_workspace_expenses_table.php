<?php

declare(strict_types=1);

use App\Enums\ExpenseCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner-tracked operating expenses for a single workspace (the mini management
 * module). Categorised and dated so the owner can review spend over a period.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_expenses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('title');
            $table->string('category')->default(ExpenseCategory::Other->value);
            $table->decimal('amount', 12, 2);
            $table->date('spent_on');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'spent_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_expenses');
    }
};
