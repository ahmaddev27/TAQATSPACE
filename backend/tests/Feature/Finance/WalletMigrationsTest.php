<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The wallet migrations run on a fresh database, twice in a row, and down and
 * up again. Schema changes commit implicitly on MariaDB, so no wrapping
 * transaction can undo these tests: the next test migrates afresh.
 */
class WalletMigrationsTest extends TestCase
{
    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;

        parent::tearDown();
    }

    public function test_migrate_fresh_runs_twice_and_builds_the_wallet_tables(): void
    {
        $this->artisan('migrate:fresh')->assertSuccessful();
        $this->artisan('migrate:fresh')->assertSuccessful();

        foreach (['customer_wallets', 'wallet_entries', 'payment_vouchers'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "{$table} exists");
        }

        $this->assertTrue(Schema::hasColumn('invoices', 'moved_to_wallet'));
        $this->assertTrue(Schema::hasIndex('customer_wallets', ['workspace_id', 'customer_id'], 'unique'));
        // 5.8 never reached production, so there is no refund_amount to copy onto wallets.
        $this->assertFileDoesNotExist(database_path('migrations/2026_11_10_110000_credit_wallets_from_existing_credit_notes.php'));
    }

    public function test_the_wallet_migrations_run_down_and_up(): void
    {
        $this->artisan('migrate:fresh')->assertSuccessful();
        $tables = require database_path('migrations/2026_11_10_120000_create_wallet_tables.php');
        $column = require database_path('migrations/2026_11_10_120001_add_moved_to_wallet_to_invoices.php');

        $column->down();
        $column->down();
        $tables->down();
        $this->assertFalse(Schema::hasTable('customer_wallets'));
        $this->assertFalse(Schema::hasColumn('invoices', 'moved_to_wallet'));

        $tables->up();
        $column->up();
        $column->up();
        $this->assertTrue(Schema::hasTable('customer_wallets'));
        $this->assertTrue(Schema::hasColumn('invoices', 'moved_to_wallet'));
    }
}
