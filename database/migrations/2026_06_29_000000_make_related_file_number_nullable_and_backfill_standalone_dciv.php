<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';
    protected $table = 'master_dciv_links';

    /**
     * A DCIV file belongs in master_dciv_links even when it has no related file.
     * This migration:
     *   1. Relaxes related_file_number to allow NULL (the standalone marker).
     *   2. Backfills a standalone row for every indexed DCIV/LPCC file that has
     *      no row in master_dciv_links yet (e.g. DCIV-2026-263).
     */
    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable($this->table)) {
            return;
        }

        // SQL Server permits NOT NULL -> NULL on an indexed nvarchar column
        // without dropping the unique index, as long as type/size are unchanged.
        DB::connection($this->connection)->statement(
            "ALTER TABLE [{$this->table}] ALTER COLUMN [related_file_number] NVARCHAR(255) NULL"
        );

        $this->backfillStandaloneDcivs();
    }

    public function down(): void
    {
        // Remove the standalone placeholder rows introduced here.
        DB::connection($this->connection)->table($this->table)
            ->whereNull('related_file_number')
            ->delete();

        // Note: related_file_number is intentionally left nullable — reverting it to
        // NOT NULL would fail if any standalone rows were added after this migration.
    }

    private function backfillStandaloneDcivs(): void
    {
        if (! Schema::connection($this->connection)->hasTable('file_indexings')) {
            return;
        }

        $hasDcivFileNo = Schema::connection($this->connection)->hasTable('dciv_file_no');

        $metaApply = $hasDcivFileNo
            ? "OUTER APPLY (
                   SELECT TOP 1 d2.id, d2.dciv_reason, d2.created_by
                   FROM [dciv_file_no] d2
                   WHERE UPPER(LTRIM(RTRIM(d2.full_file_number))) = UPPER(LTRIM(RTRIM(fi.file_number)))
                     AND (d2.is_deleted = 0 OR d2.is_deleted IS NULL)
                   ORDER BY d2.id DESC
               ) d"
            : "OUTER APPLY (SELECT CAST(NULL AS BIGINT) AS id,
                                  CAST(NULL AS NVARCHAR(1000)) AS dciv_reason,
                                  CAST(NULL AS BIGINT) AS created_by) d";

        // Stamp the master row with the DCIV's original indexing date, not the
        // migration run time, so created_at reflects when the file was created.
        DB::connection($this->connection)->statement("
            INSERT INTO [{$this->table}]
                (dciv_file_no_id, dciv_file_number, dciv_reason, related_file_number,
                 land_file_number, sltr_file_number, st_file_number,
                 related_file_type, related_file_title, created_by, created_at, updated_at)
            SELECT
                d.id,
                LTRIM(RTRIM(fi.file_number)),
                COALESCE(d.dciv_reason, fi.dciv_reason),
                NULL, NULL, NULL, NULL, NULL, NULL,
                d.created_by,
                COALESCE(fi.orig_created, SYSUTCDATETIME()),
                COALESCE(fi.orig_created, SYSUTCDATETIME())
            FROM (
                SELECT fi2.file_number, fi2.dciv_reason, fi2.created_at AS orig_created,
                       ROW_NUMBER() OVER (
                           PARTITION BY UPPER(LTRIM(RTRIM(fi2.file_number)))
                           ORDER BY fi2.created_at ASC, fi2.id ASC
                       ) AS rn
                FROM [file_indexings] fi2
                WHERE (UPPER(LTRIM(RTRIM(fi2.file_number))) LIKE 'DCIV%'
                    OR UPPER(LTRIM(RTRIM(fi2.file_number))) LIKE 'LPCC%')
                  AND (fi2.is_deleted = 0 OR fi2.is_deleted IS NULL)
            ) fi
            {$metaApply}
            WHERE fi.rn = 1
              AND NOT EXISTS (
                  SELECT 1 FROM [{$this->table}] t
                  WHERE UPPER(LTRIM(RTRIM(t.dciv_file_number))) = UPPER(LTRIM(RTRIM(fi.file_number)))
              )
        ");
    }
};
