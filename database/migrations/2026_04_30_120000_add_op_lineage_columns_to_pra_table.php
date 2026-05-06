<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds OP→ToT lineage columns to `pra` so the originating OP can be tracked
 * by its true primary key instead of by `prop_id` alone.
 *
 * Why:
 *   `prop_id` is reused across IC/PRA/DR siblings; the OSS Change-of-Name
 *   commissioning flow has produced ToT rows attached to the wrong OP because
 *   the join was by reused `prop_id`. The new columns let writers record the
 *   exact OP row the ToT derives from, regardless of which table the OP lives in.
 *
 * Columns:
 *   source_op_table  — `pra` | `instrument_capture` | `deed_registrations`
 *   source_op_id     — primary key of the originating OP row in that table
 *   parent_prop_id   — the OP's `prop_id`, preserved as lineage label even when
 *                      the ToT itself is given a fresh `prop_id`
 *
 * All columns are nullable for backward compatibility with existing rows.
 */
return new class extends Migration
{
    private const CONNECTION = 'sqlsrv';
    private const TABLE = 'pra';

    public function up(): void
    {
        if (!Schema::connection(self::CONNECTION)->hasTable(self::TABLE)) {
            return;
        }

        Schema::connection(self::CONNECTION)->table(self::TABLE, function (Blueprint $table) {
            if (!Schema::connection(self::CONNECTION)->hasColumn(self::TABLE, 'source_op_table')) {
                $table->string('source_op_table', 50)->nullable()->after('prop_id');
            }
            if (!Schema::connection(self::CONNECTION)->hasColumn(self::TABLE, 'source_op_id')) {
                $table->bigInteger('source_op_id')->nullable()->after('source_op_table');
            }
            if (!Schema::connection(self::CONNECTION)->hasColumn(self::TABLE, 'parent_prop_id')) {
                $table->string('parent_prop_id', 50)->nullable()->after('source_op_id');
            }
        });

        $this->createIndexIfMissing(
            'IX_pra_source_op',
            'CREATE INDEX [IX_pra_source_op] ON [' . self::TABLE . '] ([source_op_table], [source_op_id])'
        );
        $this->createIndexIfMissing(
            'IX_pra_parent_prop_id',
            'CREATE INDEX [IX_pra_parent_prop_id] ON [' . self::TABLE . '] ([parent_prop_id])'
        );
    }

    public function down(): void
    {
        if (!Schema::connection(self::CONNECTION)->hasTable(self::TABLE)) {
            return;
        }

        $this->dropIndexIfExists('IX_pra_source_op');
        $this->dropIndexIfExists('IX_pra_parent_prop_id');

        Schema::connection(self::CONNECTION)->table(self::TABLE, function (Blueprint $table) {
            foreach (['parent_prop_id', 'source_op_id', 'source_op_table'] as $column) {
                if (Schema::connection(self::CONNECTION)->hasColumn(self::TABLE, $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function createIndexIfMissing(string $indexName, string $createSql): void
    {
        $exists = DB::connection(self::CONNECTION)->selectOne(
            "SELECT 1 AS x FROM sys.indexes WHERE name = ? AND object_id = OBJECT_ID(?)",
            [$indexName, self::TABLE]
        );

        if (!$exists) {
            DB::connection(self::CONNECTION)->statement($createSql);
        }
    }

    private function dropIndexIfExists(string $indexName): void
    {
        $exists = DB::connection(self::CONNECTION)->selectOne(
            "SELECT 1 AS x FROM sys.indexes WHERE name = ? AND object_id = OBJECT_ID(?)",
            [$indexName, self::TABLE]
        );

        if ($exists) {
            DB::connection(self::CONNECTION)->statement(
                'DROP INDEX [' . $indexName . '] ON [' . self::TABLE . ']'
            );
        }
    }
};
