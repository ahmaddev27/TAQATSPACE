<?php

declare(strict_types=1);

use App\Services\Hotspot\LegacyCredentialsMigrator;
use Illuminate\Database\Migrations\Migration;

/**
 * Rerunnable data copy; the legacy subscriptions columns stay in place,
 * read-only, until the transition is complete.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new LegacyCredentialsMigrator)->migrate();
    }

    public function down(): void
    {
        // Nothing to undo: the legacy columns were never modified.
    }
};
