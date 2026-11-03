<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ledger of what a subscriber will be billed for later. `(workspace, source,
 * source_ref)` is unique so a producer that retries cannot double-charge.
 * `invoice_line_id` has no foreign key yet: invoice lines arrive with task 5.5.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unbilled_charges', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->foreignUuid('catalog_item_id')->nullable()->constrained('catalog_items')->nullOnDelete();
            $table->string('description');
            $table->decimal('qty', 12, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('source', 20);
            $table->string('source_ref', 100)->nullable();
            $table->string('status', 20)->default('open');
            $table->uuid('invoice_line_id')->nullable();
            $table->dateTime('occurred_at');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['workspace_id', 'source', 'source_ref']);
            $table->index(['workspace_id', 'customer_id', 'status']);
            $table->index(['workspace_id', 'status', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unbilled_charges');
    }
};
