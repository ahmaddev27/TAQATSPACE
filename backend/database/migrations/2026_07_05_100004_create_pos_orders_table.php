<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A café/POS sale. Standalone (NOT tied to a subscription like `invoices`),
 * because it serves walk-ins and members alike. Created either by a cashier at
 * the counter (`source = cashier`) or placed by a freelancer from their
 * dashboard (`source = freelancer`), then settled with one or more payments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('order_number');
            $table->string('source'); // cashier | freelancer
            $table->string('status'); // pending | paid | cancelled
            $table->foreignUuid('member_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('customer_name')->nullable();
            $table->foreignUuid('cashier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
            $table->unique(['workspace_id', 'order_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_orders');
    }
};
