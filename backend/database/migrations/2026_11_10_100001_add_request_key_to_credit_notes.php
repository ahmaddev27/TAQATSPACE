<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An optional client key per credit-note form: repeating the request with the
 * same key returns the first note instead of issuing a second credit. Unique
 * per workspace; NULL (no key) never collides, so equal credits issued on
 * purpose stay possible.
 */
return new class extends Migration
{
    private const UNIQUE = 'credit_notes_workspace_request_key_unique';

    public function up(): void
    {
        if (Schema::hasColumn('credit_notes', 'request_key')) {
            return;
        }

        Schema::table('credit_notes', function (Blueprint $table): void {
            $table->string('request_key', 64)->nullable()->after('reason');
            $table->unique(['workspace_id', 'request_key'], self::UNIQUE);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('credit_notes', 'request_key')) {
            return;
        }

        Schema::table('credit_notes', function (Blueprint $table): void {
            $table->dropUnique(self::UNIQUE);
        });

        Schema::table('credit_notes', function (Blueprint $table): void {
            $table->dropColumn('request_key');
        });
    }
};
