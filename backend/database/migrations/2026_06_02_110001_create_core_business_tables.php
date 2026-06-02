<?php

use App\Enums\InvoiceStatus;
use App\Enums\PlanType;
use App\Enums\SeatStatus;
use App\Enums\SeatType;
use App\Enums\SubscriptionStatus;
use App\Enums\WorkspaceStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T007 — Core business entities: workspaces, seats, subscriptions, invoices.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('address');
            $table->string('city')->index();
            $table->string('phone')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('total_seats')->default(0);
            $table->decimal('price_per_month', 8, 2)->default(0);
            $table->json('amenities')->nullable();          // ["wifi","printer",...]
            $table->json('photos')->nullable();             // array of S3 paths
            $table->json('working_hours')->nullable();      // per-day open/close
            $table->string('status')->default(WorkspaceStatus::Pending->value)->index();
            $table->decimal('avg_rating', 3, 2)->default(0); // denormalized
            $table->timestamps();
            $table->softDeletes();

            $table->index('price_per_month');
            $table->index('avg_rating');
        });

        Schema::create('seats', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('seat_number');
            $table->string('type')->default(SeatType::Flexible->value);
            $table->string('status')->default(SeatStatus::Available->value);
            $table->foreignUuid('assigned_member_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'seat_number']);
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('member_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignUuid('seat_id')->nullable()->constrained('seats')->nullOnDelete();
            $table->string('plan_type')->default(PlanType::Monthly->value);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('monthly_price', 8, 2);
            $table->string('status')->default(SubscriptionStatus::Pending->value)->index();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->decimal('amount', 8, 2);
            $table->date('due_date')->index();
            $table->timestamp('paid_at')->nullable();
            $table->string('status')->default(InvoiceStatus::Pending->value)->index();
            $table->string('invoice_number')->unique();      // TAQAT-{YYYY}-{0000}
            $table->string('invoice_pdf_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('seats');
        Schema::dropIfExists('workspaces');
    }
};
