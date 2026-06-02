<?php

use App\Enums\AnnouncementType;
use App\Enums\BookingStatus;
use App\Enums\MessageType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T008 — Supporting tables: internet_packages, booking_requests, messages,
 * announcements, reviews, and the member_package pivot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internet_packages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('speed_mbps');
            $table->decimal('price', 8, 2)->default(0);     // monthly add-on
            $table->unsignedInteger('data_limit_gb')->nullable();
            $table->boolean('is_unlimited')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('booking_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('member_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('preferred_seat_type')->nullable();
            $table->text('message')->nullable();
            $table->string('status')->default(BookingStatus::Pending->value)->index();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('receiver_id')->nullable()->constrained('users')->nullOnDelete(); // null = broadcast
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('type')->default(MessageType::Direct->value);
            $table->text('content');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->nullable()->constrained('workspaces')->cascadeOnDelete(); // null = platform-wide
            $table->string('title');
            $table->text('body');
            $table->string('type')->default(AnnouncementType::Info->value);
            $table->timestamp('published_at')->nullable();  // null = draft
            $table->timestamp('expires_at')->nullable()->index();
            $table->foreignUuid('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('member_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');          // 1..5
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['member_id', 'workspace_id']);  // one review per member per workspace
        });

        Schema::create('member_package', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('member_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('package_id')->constrained('internet_packages')->cascadeOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();

            $table->unique(['member_id', 'package_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_package');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('booking_requests');
        Schema::dropIfExists('internet_packages');
    }
};
