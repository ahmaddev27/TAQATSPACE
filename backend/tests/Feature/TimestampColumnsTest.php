<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Before MariaDB 10.10 the first NOT NULL TIMESTAMP of a table silently gets
 * ON UPDATE CURRENT_TIMESTAMP, so any update to the row rewrites it: a payment's
 * paid_at or a hotspot event's occurred_at would move to the time of the update.
 * A column needs an explicit default, or the DATETIME type, to stay out of this.
 */
class TimestampColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_column_rewrites_itself_when_its_row_is_updated(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->markTestSkipped('SQLite has no ON UPDATE clause.');
        }

        $columns = DB::select(
            "select table_name as table_name_, column_name as column_name_
             from information_schema.columns
             where table_schema = database() and extra like '%on update%'
             order by table_name, column_name"
        );

        $this->assertSame([], array_map(
            fn (object $column): string => "{$column->table_name_}.{$column->column_name_}",
            $columns,
        ));
    }
}
