<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Second backfill source for master_dciv_links.
 *
 * The original create_master_dciv_links migration only sourced related-file links
 * from the `dciv_link` table (the commissioning workflow's link store). DCIV/LPCC
 * files that were brought in through the file-indexing module instead of the DCIV
 * commissioning UI carry their related files in `file_indexing_links` (keyed by
 * file_indexing_id), and were therefore never represented in master_dciv_links.
 *
 * This migration backfills those, mirroring the type-classification and de-dup
 * logic of the original. It is additive and idempotent (NOT EXISTS guard).
 */
return new class extends Migration
{
    protected $connection = 'sqlsrv';
    protected $table = 'master_dciv_links';

    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable($this->table)
            || ! Schema::connection($this->connection)->hasTable('file_indexing_links')
            || ! Schema::connection($this->connection)->hasTable('file_indexings')) {
            return;
        }

        // Shared type-classification expression (kept in sync with MasterDcivLink::classifyFileType
        // and the create_master_dciv_links backfill).
        $typeExpr = "
            CASE
                WHEN UPPER(LTRIM(RTRIM(l.file_number))) LIKE 'SLTR%' THEN 'SLTR'
                WHEN UPPER(LTRIM(RTRIM(l.file_number))) LIKE 'ST-%'
                  OR UPPER(LTRIM(RTRIM(l.file_number))) LIKE 'ST %' THEN 'ST'
                WHEN UPPER(LTRIM(RTRIM(l.file_number))) LIKE 'RES%'
                  OR UPPER(LTRIM(RTRIM(l.file_number))) LIKE 'COM%'
                  OR UPPER(LTRIM(RTRIM(l.file_number))) LIKE 'IND%'
                  OR UPPER(LTRIM(RTRIM(l.file_number))) LIKE 'AG%'
                  OR UPPER(LTRIM(RTRIM(l.file_number))) LIKE 'MIXED%'
                  OR UPPER(LTRIM(RTRIM(l.file_number))) LIKE 'CON%'
                  OR UPPER(LTRIM(RTRIM(l.file_number))) LIKE 'KN%'
                  OR UPPER(LTRIM(RTRIM(l.file_number))) LIKE 'MISC%'
                  OR UPPER(LTRIM(RTRIM(l.file_number))) LIKE 'SIT%'
                  OR UPPER(LTRIM(RTRIM(l.file_number))) LIKE 'GKN%'
                  OR UPPER(LTRIM(RTRIM(l.file_number))) LIKE 'LPKN%' THEN 'Land'
                ELSE 'Other'
            END
        ";

        DB::connection($this->connection)->statement("
            INSERT INTO [{$this->table}]
                (dciv_file_no_id, dciv_file_number, dciv_reason, related_file_number,
                 land_file_number, sltr_file_number, st_file_number,
                 related_file_type, related_file_title, created_by, created_at, updated_at)
            SELECT
                d.id,
                LTRIM(RTRIM(fi.file_number)),
                COALESCE(d.dciv_reason, fi.dciv_reason),
                LTRIM(RTRIM(l.file_number)),
                CASE WHEN {$typeExpr} = 'Land' THEN LTRIM(RTRIM(l.file_number)) END,
                CASE WHEN {$typeExpr} = 'SLTR' THEN LTRIM(RTRIM(l.file_number)) END,
                CASE WHEN {$typeExpr} = 'ST'   THEN LTRIM(RTRIM(l.file_number)) END,
                {$typeExpr},
                COALESCE(l.file_title, rfi.file_title),
                d.created_by,
                SYSUTCDATETIME(),
                SYSUTCDATETIME()
            FROM (
                -- One row per (dciv file_indexing_id, related file_number); source may hold dups.
                SELECT l2.file_indexing_id, l2.file_number, l2.file_title,
                       ROW_NUMBER() OVER (
                           PARTITION BY l2.file_indexing_id,
                                        UPPER(LTRIM(RTRIM(l2.file_number)))
                           ORDER BY l2.id DESC
                       ) AS rn
                FROM [file_indexing_links] l2
                WHERE l2.deleted_at IS NULL
                  AND l2.file_number IS NOT NULL
                  AND LEN(LTRIM(RTRIM(l2.file_number))) > 0
                  AND l2.file_indexing_id IS NOT NULL
            ) l
            -- Parent must be an indexed (non-commissioned) DCIV/LPCC file.
            INNER JOIN [file_indexings] fi
                ON fi.id = l.file_indexing_id
               AND fi.registry IN ('DCIV', 'LPCC')
               AND (fi.is_deleted = 0 OR fi.is_deleted IS NULL)
               AND (fi.source IS NULL OR fi.source <> 'DCIV FileNo Commissioning')
               AND fi.file_number IS NOT NULL
               AND LEN(LTRIM(RTRIM(fi.file_number))) > 0
            -- Pull a dciv_file_no row (id / reason / created_by) when one exists for this number.
            OUTER APPLY (
                SELECT TOP 1 d2.id, d2.dciv_reason, d2.created_by
                FROM [dciv_file_no] d2
                WHERE UPPER(LTRIM(RTRIM(d2.full_file_number))) = UPPER(LTRIM(RTRIM(fi.file_number)))
                  AND (d2.is_deleted = 0 OR d2.is_deleted IS NULL)
                ORDER BY d2.id DESC
            ) d
            -- Resolve a related-file title from file_indexings when the link row lacks one.
            OUTER APPLY (
                SELECT TOP 1 rfi2.file_title
                FROM [file_indexings] rfi2
                WHERE UPPER(LTRIM(RTRIM(rfi2.file_number))) = UPPER(LTRIM(RTRIM(l.file_number)))
                ORDER BY rfi2.id DESC
            ) rfi
            WHERE l.rn = 1
              AND NOT EXISTS (
                  SELECT 1 FROM [{$this->table}] t
                  WHERE UPPER(LTRIM(RTRIM(t.dciv_file_number)))    = UPPER(LTRIM(RTRIM(fi.file_number)))
                    AND UPPER(LTRIM(RTRIM(t.related_file_number))) = UPPER(LTRIM(RTRIM(l.file_number)))
              )
        ");
    }

    public function down(): void
    {
        // Non-reversible data backfill; rows are intentionally left in place.
    }
};
