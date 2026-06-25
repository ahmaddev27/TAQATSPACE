<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-broadcast messaging usage, so the admin can see which workspaces send
 * through Taqat's shared accounts ("platform" quota) vs. their own, and how many
 * messages they sent on each channel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_usage', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('channel');  // email | sms
            $table->string('source');   // platform | own
            $table->unsignedInteger('count');
            $table->timestamps();

            $table->index(['workspace_id', 'source']);
            $table->index(['source', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_usage');
    }
};
