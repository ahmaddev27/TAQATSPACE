<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guards the CI matrix: phpunit.xml pins DB_CONNECTION=sqlite without `force`,
 * so a job meant to run on MariaDB could silently fall back to sqlite and
 * stay green. CI declares what it expects via EXPECT_DB_DRIVER and
 * EXPECT_DB_SERVER; local runs default to sqlite.
 */
class DatabaseDriverTest extends TestCase
{
    public function test_tests_run_on_the_database_the_job_declares(): void
    {
        $expected = getenv('EXPECT_DB_DRIVER') ?: 'sqlite';

        $this->assertSame($expected, DB::connection()->getDriverName());
    }

    public function test_server_is_the_declared_engine(): void
    {
        $server = getenv('EXPECT_DB_SERVER');

        if ($server === false || $server === '') {
            $this->assertSame('sqlite', DB::connection()->getDriverName());

            return;
        }

        $version = (string) DB::selectOne('select version() as v')->v;

        $this->assertStringContainsStringIgnoringCase($server, $version);
    }
}
