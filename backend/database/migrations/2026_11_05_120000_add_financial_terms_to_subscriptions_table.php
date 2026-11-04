<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Financial terms of a subscription: the plan it follows, prepaid/postpaid
 * billing, a negotiated price and contracted hours. Additive only: existing
 * rows keep working as postpaid subscriptions with no plan, priced by the
 * legacy `monthly_price`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->foreignUuid('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->string('billing_mode', 10)->default('postpaid');
            $table->decimal('custom_price', 12, 2)->nullable();
            $table->decimal('contracted_hours', 12, 2)->nullable();
            $table->unsignedSmallInteger('grace_days')->default(0);
            $table->boolean('auto_renew')->default(false);
            $table->uuid('renewed_from_id')->nullable();

            $table->index('renewed_from_id');
            $table->index(['workspace_id', 'billing_mode']);
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropForeign(['plan_id']);
            $table->dropIndex(['workspace_id', 'billing_mode']);
            $table->dropIndex(['renewed_from_id']);
            $table->dropColumn([
                'plan_id', 'billing_mode', 'custom_price', 'contracted_hours',
                'grace_days', 'auto_renew', 'renewed_from_id',
            ]);
        });
    }
};
