<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-wide prepaid hour packages. Deliberately no workspace_id on the
 * catalog or purchases: the hours belong to the user, not to one workspace.
 * The ledger is high-volume and append-only, so it uses a BIGINT key; its
 * workspace_id is a plain UUID column (no foreign key) so entries outlive it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hour_packages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();
            $table->string('name_ar', 120);
            $table->string('name_en', 120);
            $table->unsignedInteger('hours');
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->unsignedInteger('validity_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('hour_package_purchases', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('hour_package_id')->constrained('hour_packages');
            $table->unsignedInteger('hours');
            $table->decimal('price', 10, 2);
            $table->string('currency', 3);
            $table->string('status', 20)->default('requested');
            $table->timestamp('activated_at')->nullable();
            $table->uuid('activated_by')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('payment_method', 20)->nullable();
            $table->string('receipt_path')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('hour_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('hour_package_purchase_id')->nullable();
            $table->uuid('workspace_id')->nullable();
            $table->integer('minutes');
            $table->string('reason', 20);
            $table->decimal('rate', 10, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('source_type', 100)->nullable();
            $table->string('source_id', 64)->nullable();
            $table->string('note', 500)->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index('hour_package_purchase_id');
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hour_ledger_entries');
        Schema::dropIfExists('hour_package_purchases');
        Schema::dropIfExists('hour_packages');
    }
};
