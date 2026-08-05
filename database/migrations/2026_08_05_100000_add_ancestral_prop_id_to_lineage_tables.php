<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `ancestral_prop_id` — the third and topmost level of the parcel lineage
 * cascade:
 *
 *     Ancestral PropID
 *       └── Parent PropID
 *             └── PropID
 *
 * Why:
 *   Merged / subdivided / consolidated files can be more than two generations
 *   deep. Today only `prop_id` and `parent_prop_id` are stored, so anything
 *   above the immediate parent has to be recovered by walking the chain at read
 *   time (LegalSearchService::resolveAncestorPropIds). Storing the root of that
 *   chain makes "which parcel family does this belong to" a single indexed
 *   column, which is what multi-level merger control needs.
 *
 * Semantics:
 *   ancestral_prop_id = the ROOT prop_id of the parent chain (oldest generation).
 *   A row with no parent is itself a root and is left NULL — the column never
 *   points at its own prop_id.
 *
 * Scope:
 *   Only the four tables that already carry `parent_prop_id`. `file_history_staging`
 *   and `deed_registrations` have no parent level, so an ancestral level above it
 *   would have nothing to sit on.
 *
 * Populated by App\Services\PropIdLineageService and the re-runnable
 * `propid:backfill-ancestral` command.
 */
return new class extends Migration
{
    private const CONNECTION = 'sqlsrv';

    /** table => index name */
    private const TABLES = [
        'pra' => 'IX_pra_ancestral_prop_id',
        'CofO_staging' => 'IX_CofO_staging_ancestral_prop_id',
        'file_indexings' => 'IX_file_indexings_ancestral_prop_id',
        'fileNumber' => 'IX_fileNumber_ancestral_prop_id',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table => $indexName) {
            if (!Schema::connection(self::CONNECTION)->hasTable($table)) {
                continue;
            }

            Schema::connection(self::CONNECTION)->table($table, function (Blueprint $blueprint) use ($table) {
                if (!Schema::connection(self::CONNECTION)->hasColumn($table, 'ancestral_prop_id')) {
                    $blueprint->string('ancestral_prop_id', 50)->nullable();
                }
            });

            $this->createIndexIfMissing(
                $table,
                $indexName,
                'CREATE INDEX [' . $indexName . '] ON [' . $table . '] ([ancestral_prop_id])'
            );
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table => $indexName) {
            if (!Schema::connection(self::CONNECTION)->hasTable($table)) {
                continue;
            }

            $this->dropIndexIfExists($table, $indexName);

            Schema::connection(self::CONNECTION)->table($table, function (Blueprint $blueprint) use ($table) {
                if (Schema::connection(self::CONNECTION)->hasColumn($table, 'ancestral_prop_id')) {
                    $blueprint->dropColumn('ancestral_prop_id');
                }
            });
        }
    }

    private function createIndexIfMissing(string $table, string $indexName, string $createSql): void
    {
        $exists = DB::connection(self::CONNECTION)->selectOne(
            "SELECT 1 AS x FROM sys.indexes WHERE name = ? AND object_id = OBJECT_ID(?)",
            [$indexName, $table]
        );

        if (!$exists) {
            DB::connection(self::CONNECTION)->statement($createSql);
        }
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        $exists = DB::connection(self::CONNECTION)->selectOne(
            "SELECT 1 AS x FROM sys.indexes WHERE name = ? AND object_id = OBJECT_ID(?)",
            [$indexName, $table]
        );

        if ($exists) {
            DB::connection(self::CONNECTION)->statement(
                'DROP INDEX [' . $indexName . '] ON [' . $table . ']'
            );
        }
    }
};
