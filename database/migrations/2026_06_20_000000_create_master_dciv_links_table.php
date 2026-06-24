<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';
    protected $table = 'master_dciv_links';

    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable($this->table)) {
            Schema::connection($this->connection)->create($this->table, function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('dciv_file_no_id')->nullable();
                $table->string('dciv_file_number', 100);
                $table->string('dciv_reason', 1000)->nullable();
                $table->string('related_file_number', 255);
                $table->string('land_file_number', 255)->nullable();
                $table->string('sltr_file_number', 255)->nullable();
                $table->string('st_file_number', 255)->nullable();
                $table->string('related_file_type', 20)->nullable();
                $table->string('related_file_title', 500)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index('dciv_file_no_id');
                $table->index('dciv_file_number');
                $table->index('related_file_number');
                $table->unique(['dciv_file_number', 'related_file_number'], 'uq_mdl_dciv_related');
            });
        }

        $this->backfillFromDcivLink();
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists($this->table);
    }

    /**
     * Populate master_dciv_links from the existing dciv_link table, enriching
     * each row with the DCIV reason (dciv_file_no) and the related file's title
     * (file_indexings), and classifying the related file by type.
     */
    private function backfillFromDcivLink(): void
    {
        if (! Schema::connection($this->connection)->hasTable('dciv_link')) {
            return;
        }

        // Shared type-classification expression (kept in sync with MasterDcivLink::classifyFileType).
        $typeExpr = "
            CASE
                WHEN UPPER(LTRIM(RTRIM(l.related_file_number))) LIKE 'SLTR%' THEN 'SLTR'
                WHEN UPPER(LTRIM(RTRIM(l.related_file_number))) LIKE 'ST-%'
                  OR UPPER(LTRIM(RTRIM(l.related_file_number))) LIKE 'ST %' THEN 'ST'
                WHEN UPPER(LTRIM(RTRIM(l.related_file_number))) LIKE 'RES%'
                  OR UPPER(LTRIM(RTRIM(l.related_file_number))) LIKE 'COM%'
                  OR UPPER(LTRIM(RTRIM(l.related_file_number))) LIKE 'IND%'
                  OR UPPER(LTRIM(RTRIM(l.related_file_number))) LIKE 'AG%'
                  OR UPPER(LTRIM(RTRIM(l.related_file_number))) LIKE 'MIXED%'
                  OR UPPER(LTRIM(RTRIM(l.related_file_number))) LIKE 'CON%'
                  OR UPPER(LTRIM(RTRIM(l.related_file_number))) LIKE 'KN%'
                  OR UPPER(LTRIM(RTRIM(l.related_file_number))) LIKE 'MISC%'
                  OR UPPER(LTRIM(RTRIM(l.related_file_number))) LIKE 'SIT%'
                  OR UPPER(LTRIM(RTRIM(l.related_file_number))) LIKE 'GKN%'
                  OR UPPER(LTRIM(RTRIM(l.related_file_number))) LIKE 'LPKN%' THEN 'Land'
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
                LTRIM(RTRIM(l.main_file_number)),
                d.dciv_reason,
                LTRIM(RTRIM(l.related_file_number)),
                CASE WHEN {$typeExpr} = 'Land' THEN LTRIM(RTRIM(l.related_file_number)) END,
                CASE WHEN {$typeExpr} = 'SLTR' THEN LTRIM(RTRIM(l.related_file_number)) END,
                CASE WHEN {$typeExpr} = 'ST'   THEN LTRIM(RTRIM(l.related_file_number)) END,
                {$typeExpr},
                COALESCE(l.secondary_title, fi.file_title),
                d.created_by,
                SYSUTCDATETIME(),
                SYSUTCDATETIME()
            FROM (
                -- Dedupe dciv_link to one row per (main, related) pair (source may contain duplicates).
                SELECT l2.main_file_number, l2.related_file_number, l2.secondary_title,
                       ROW_NUMBER() OVER (
                           PARTITION BY UPPER(LTRIM(RTRIM(l2.main_file_number))),
                                        UPPER(LTRIM(RTRIM(l2.related_file_number)))
                           ORDER BY l2.id DESC
                       ) AS rn
                FROM [dciv_link] l2
                WHERE l2.related_file_number IS NOT NULL
                  AND LEN(LTRIM(RTRIM(l2.related_file_number))) > 0
            ) l
            OUTER APPLY (
                SELECT TOP 1 d2.id, d2.dciv_reason, d2.created_by
                FROM [dciv_file_no] d2
                WHERE UPPER(LTRIM(RTRIM(d2.full_file_number))) = UPPER(LTRIM(RTRIM(l.main_file_number)))
                  AND (d2.is_deleted = 0 OR d2.is_deleted IS NULL)
                ORDER BY d2.id DESC
            ) d
            OUTER APPLY (
                SELECT TOP 1 fi2.file_title
                FROM [file_indexings] fi2
                WHERE UPPER(LTRIM(RTRIM(fi2.file_number))) = UPPER(LTRIM(RTRIM(l.related_file_number)))
                ORDER BY fi2.id DESC
            ) fi
            WHERE l.rn = 1
              AND NOT EXISTS (
                  SELECT 1 FROM [{$this->table}] t
                  WHERE UPPER(LTRIM(RTRIM(t.dciv_file_number)))    = UPPER(LTRIM(RTRIM(l.main_file_number)))
                    AND UPPER(LTRIM(RTRIM(t.related_file_number))) = UPPER(LTRIM(RTRIM(l.related_file_number)))
              )
        ");
    }
};
