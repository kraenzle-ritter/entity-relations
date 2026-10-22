<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Additive only. Creates the relations table where it does not exist yet
 * (same layout as in Anton since 2022) and adds nullable columns for the
 * primary parent, ordering and a time span. Existing rows stay valid.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = config('entity-relations.table', 'relations');

        if (! Schema::hasTable($table)) {
            Schema::create($table, function (Blueprint $t) {
                $t->id();
                $t->string('name')->nullable();
                $t->text('comment')->nullable();
                $t->text('label')->nullable();
                $t->string('source_type');
                $t->integer('source_id');
                $t->string('target_type');
                $t->integer('target_id');
                $t->index(['source_id', 'source_type'], 'source_index');
                $t->index(['target_id', 'target_type'], 'target_index');
            });
        }

        $columns = [
            'is_primary' => fn (Blueprint $t) => $t->boolean('is_primary')->default(false),
            'position' => fn (Blueprint $t) => $t->unsignedInteger('position')->nullable(),
            'date_text' => fn (Blueprint $t) => $t->string('date_text')->nullable(),
            'date_start' => fn (Blueprint $t) => $t->date('date_start')->nullable(),
            'date_end' => fn (Blueprint $t) => $t->date('date_end')->nullable(),
        ];

        foreach ($columns as $column => $add) {
            if (! Schema::hasColumn($table, $column)) {
                Schema::table($table, fn (Blueprint $t) => $add($t));
            }
        }

        if (! Schema::hasIndex($table, 'relations_source_name_index')) {
            Schema::table($table, fn (Blueprint $t) => $t->index(['source_type', 'source_id', 'name'], 'relations_source_name_index'));
        }
        if (! Schema::hasIndex($table, 'relations_target_name_index')) {
            Schema::table($table, fn (Blueprint $t) => $t->index(['target_type', 'target_id', 'name'], 'relations_target_name_index'));
        }
    }

    public function down(): void
    {
        $table = config('entity-relations.table', 'relations');

        Schema::table($table, function (Blueprint $t) use ($table) {
            foreach (['relations_source_name_index', 'relations_target_name_index'] as $index) {
                if (Schema::hasIndex($table, $index)) {
                    $t->dropIndex($index);
                }
            }
        });

        foreach (['is_primary', 'position', 'date_text', 'date_start', 'date_end'] as $column) {
            if (Schema::hasColumn($table, $column)) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn($column));
            }
        }
    }
};
