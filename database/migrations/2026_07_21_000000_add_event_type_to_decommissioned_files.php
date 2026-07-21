<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds a typed provenance column to decommissioned_files so the Legal Search timeline can
 * apply the client's decommission rules:
 *   - 'parcel_update_new'   : a genuine KLAES parcel-update decommission (PlotWorkflowService)
 *                             -> the File Decommissioning row shows the real Date Decommissioned.
 *   - 'title_status_update' : a genuine title-status decommission (fd=0) -> shows the real date
 *                             AND the File Decommissioning row is the LAST line on the timeline.
 *   - 'title_status_flag'   : a title-status FLAG raised from File Indexing (false_decommissioning=1,
 *                             the file is NOT actually decommissioned) -> stays suppressed as today.
 *   - 'backfill'            : reconstructed lineage ("Manual Linkage: …") -> Transaction Date left
 *                             empty ("back linkage").
 *
 * Existing rows are classified deterministically from false_decommissioning + the reason prefix.
 */
return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        $exists = DB::connection('sqlsrv')->selectOne(
            "SELECT 1 AS found FROM sys.columns WHERE object_id = OBJECT_ID('decommissioned_files') AND name = ?",
            ['event_type']
        );

        if (!$exists) {
            DB::connection('sqlsrv')->statement(
                "ALTER TABLE decommissioned_files ADD event_type NVARCHAR(40) NULL"
            );
        }

        // Deterministic backfill of existing rows.
        DB::connection('sqlsrv')->statement(
            "UPDATE decommissioned_files SET event_type = CASE
                WHEN false_decommissioning = 1 THEN 'title_status_flag'
                WHEN decommissioning_reason LIKE 'Manual Linkage:%' THEN 'backfill'
                WHEN decommissioning_reason LIKE 'Title Status:%' THEN 'title_status_update'
                ELSE 'parcel_update_new'
             END
             WHERE event_type IS NULL"
        );
    }

    public function down(): void
    {
        $exists = DB::connection('sqlsrv')->selectOne(
            "SELECT 1 AS found FROM sys.columns WHERE object_id = OBJECT_ID('decommissioned_files') AND name = ?",
            ['event_type']
        );

        if ($exists) {
            DB::connection('sqlsrv')->statement(
                "ALTER TABLE decommissioned_files DROP COLUMN event_type"
            );
        }
    }
};
