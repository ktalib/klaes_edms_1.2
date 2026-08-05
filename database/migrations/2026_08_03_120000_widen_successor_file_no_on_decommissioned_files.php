<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A batch subdivision retires one mother into every child at once and stores that child
 * list as CSV text. Two columns were too narrow to hold it, and both are written by
 * PlotWorkflowService::decommissionFiles():
 *
 *   decommissioned_files.successor_file_no  nvarchar(255) — the CSV child list
 *   deprecated_records.workflow_type        nvarchar(100) — the whole reason string,
 *                                           "Subdivision → CON-…, CON-…"
 *
 * A 118-child subdivision produced ~2,100 characters, so both INSERTs failed with
 * "String or binary data would be truncated". The service catches per file, so the
 * failure was swallowed: the children were linked and the mother stayed active
 * (CON-RES-2024-308, CON-AG-2022-49 — 3 Aug 2026).
 *
 * Both are widened to nvarchar(max). successor_file_no's index is dropped rather than
 * recreated: every read is either LIKE '%…%' or UPPER(LTRIM(RTRIM(col))) = ?, so it was
 * never seekable, and nvarchar(max) cannot be an index key at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('sqlsrv')->hasColumn('decommissioned_files', 'successor_file_no')) {
            DB::connection('sqlsrv')->statement(
                "IF EXISTS (SELECT 1 FROM sys.indexes
                            WHERE name = 'decommissioned_files_successor_file_no_index'
                              AND object_id = OBJECT_ID('decommissioned_files'))
                    DROP INDEX [decommissioned_files_successor_file_no_index] ON [decommissioned_files]"
            );

            DB::connection('sqlsrv')->statement(
                'ALTER TABLE [decommissioned_files] ALTER COLUMN [successor_file_no] NVARCHAR(MAX) NULL'
            );
        }

        if (Schema::connection('sqlsrv')->hasColumn('deprecated_records', 'workflow_type')) {
            DB::connection('sqlsrv')->statement(
                'ALTER TABLE [deprecated_records] ALTER COLUMN [workflow_type] NVARCHAR(MAX) NULL'
            );
        }
    }

    public function down(): void
    {
        // Lossy by nature: anything over the old limit must be cut before the columns can
        // shrink back, so trim first and then restore the original shapes + index.
        if (Schema::connection('sqlsrv')->hasColumn('deprecated_records', 'workflow_type')) {
            DB::connection('sqlsrv')->statement(
                'UPDATE [deprecated_records] SET [workflow_type] = LEFT([workflow_type], 100)
                 WHERE LEN([workflow_type]) > 100'
            );

            DB::connection('sqlsrv')->statement(
                'ALTER TABLE [deprecated_records] ALTER COLUMN [workflow_type] NVARCHAR(100) NULL'
            );
        }

        if (!Schema::connection('sqlsrv')->hasColumn('decommissioned_files', 'successor_file_no')) {
            return;
        }

        DB::connection('sqlsrv')->statement(
            'UPDATE [decommissioned_files] SET [successor_file_no] = LEFT([successor_file_no], 255)
             WHERE LEN([successor_file_no]) > 255'
        );

        DB::connection('sqlsrv')->statement(
            'ALTER TABLE [decommissioned_files] ALTER COLUMN [successor_file_no] NVARCHAR(255) NULL'
        );

        DB::connection('sqlsrv')->statement(
            "IF NOT EXISTS (SELECT 1 FROM sys.indexes
                            WHERE name = 'decommissioned_files_successor_file_no_index'
                              AND object_id = OBJECT_ID('decommissioned_files'))
                CREATE INDEX [decommissioned_files_successor_file_no_index]
                    ON [decommissioned_files] ([successor_file_no])"
        );
    }
};
