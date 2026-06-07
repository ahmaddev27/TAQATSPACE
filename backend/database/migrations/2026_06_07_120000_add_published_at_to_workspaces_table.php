<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public-publish gate, separate from the account `status`. A workspace may be
 * account-approved (`status = active`) yet still hidden from public discovery
 * until an admin explicitly publishes it. A non-null `published_at` marks the
 * workspace as publicly published; the column is indexed because every public
 * discovery query filters on it alongside `status`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table): void {
            $table->timestamp('published_at')->nullable()->after('status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table): void {
            $table->dropColumn('published_at');
        });
    }
};
