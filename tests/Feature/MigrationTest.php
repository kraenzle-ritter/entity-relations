<?php

namespace KraenzleRitter\EntityRelations\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use KraenzleRitter\EntityRelations\Tests\TestCase;

class MigrationTest extends TestCase
{
    public function test_adds_nullable_columns_and_runs_twice_without_harm(): void
    {
        DB::table('relations')->insert(['name' => 'references', 'source_type' => 'page', 'source_id' => 1, 'target_type' => 'actor', 'target_id' => 2]);

        $migration = require __DIR__.'/../../database/migrations/2026_10_22_000000_extend_relations_table.php';
        $migration->up();

        foreach (['is_primary', 'position', 'date_text', 'date_start', 'date_end'] as $column) {
            $this->assertTrue(Schema::hasColumn('relations', $column), $column);
        }
        $row = DB::table('relations')->sole();
        $this->assertSame('references', $row->name);
        $this->assertEquals(0, $row->is_primary);
        $this->assertNull($row->date_start);
    }
}
