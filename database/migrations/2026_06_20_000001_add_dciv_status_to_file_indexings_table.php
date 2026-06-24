<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';
    protected $table = 'file_indexings';

    public function up(): void
    {
        Schema::connection($this->connection)->table($this->table, function (Blueprint $table) {
            if (! Schema::connection($this->connection)->hasColumn($this->table, 'dciv_status')) {
                $table->tinyInteger('dciv_status')->default(0);
            }
            if (! Schema::connection($this->connection)->hasColumn($this->table, 'dciv_fileno')) {
                $table->string('dciv_fileno', 100)->nullable();
            }
            // dciv_reason already exists on file_indexings — reused, not re-added.
        });

        $this->backfillFromDcivLink();
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table($this->table, function (Blueprint $table) {
            if (Schema::connection($this->connection)->hasColumn($this->table, 'dciv_status')) {
                $table->dropColumn('dciv_status');
            }
            if (Schema::connection($this->connection)->hasColumn($this->table, 'dciv_fileno')) {
                $table->dropColumn('dciv_fileno');
            }
        });
    }

    /**
     * Flag every indexed file that is referenced as a related file by a DCIV
     * record. Most-recent DCIV link wins (highest dciv_link.id).
     */
    private function backfillFromDcivLink(): void
    {
        if (! Schema::connection($this->connection)->hasTable('dciv_link')) {
            return;
        }

        DB::connection($this->connection)->statement("
            UPDATE fi
            SET fi.dciv_status = 1,
                fi.dciv_fileno = link.main_file_number,
                fi.dciv_reason = link.dciv_reason
            FROM [file_indexings] fi
            INNER JOIN (
                SELECT
                    UPPER(LTRIM(RTRIM(l.related_file_number))) AS related_key,
                    l.main_file_number,
                    d.dciv_reason,
                    ROW_NUMBER() OVER (
                        PARTITION BY UPPER(LTRIM(RTRIM(l.related_file_number)))
                        ORDER BY l.id DESC
                    ) AS rn
                FROM [dciv_link] l
                OUTER APPLY (
                    SELECT TOP 1 d2.dciv_reason
                    FROM [dciv_file_no] d2
                    WHERE UPPER(LTRIM(RTRIM(d2.full_file_number))) = UPPER(LTRIM(RTRIM(l.main_file_number)))
                      AND (d2.is_deleted = 0 OR d2.is_deleted IS NULL)
                    ORDER BY d2.id DESC
                ) d
                WHERE l.related_file_number IS NOT NULL
                  AND LEN(LTRIM(RTRIM(l.related_file_number))) > 0
            ) link
                ON link.related_key = UPPER(LTRIM(RTRIM(fi.file_number)))
               AND link.rn = 1
        ");
    }
};
