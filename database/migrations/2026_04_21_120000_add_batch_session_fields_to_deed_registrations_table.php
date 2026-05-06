<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        $connection = 'sqlsrv';

        $hasBatchSessionId = Schema::connection($connection)->hasColumn('deed_registrations', 'batch_session_id');
        $hasBatchPrintedAt = Schema::connection($connection)->hasColumn('deed_registrations', 'batch_printed_at');
        $hasBatchPrintedBy = Schema::connection($connection)->hasColumn('deed_registrations', 'batch_printed_by');

        if (!$hasBatchSessionId || !$hasBatchPrintedAt || !$hasBatchPrintedBy) {
            Schema::connection($connection)->table('deed_registrations', function (Blueprint $table) use ($hasBatchSessionId, $hasBatchPrintedAt, $hasBatchPrintedBy) {
                if (!$hasBatchSessionId) {
                    $table->string('batch_session_id', 100)->nullable();
                }

                if (!$hasBatchPrintedAt) {
                    $table->dateTime('batch_printed_at')->nullable();
                }

                if (!$hasBatchPrintedBy) {
                    $table->unsignedBigInteger('batch_printed_by')->nullable();
                }
            });
        }

        DB::connection($connection)->statement("\n            IF NOT EXISTS (\n                SELECT 1\n                FROM sys.indexes\n                WHERE name = 'idx_deed_reg_batch_session_printed'\n                  AND object_id = OBJECT_ID('deed_registrations')\n            )\n            CREATE INDEX idx_deed_reg_batch_session_printed\n            ON deed_registrations(batch_session_id, batch_printed_at)\n        ");
    }

    public function down()
    {
        $connection = 'sqlsrv';

        DB::connection($connection)->statement("\n            IF EXISTS (\n                SELECT 1\n                FROM sys.indexes\n                WHERE name = 'idx_deed_reg_batch_session_printed'\n                  AND object_id = OBJECT_ID('deed_registrations')\n            )\n            DROP INDEX idx_deed_reg_batch_session_printed ON deed_registrations\n        ");

        $dropColumns = [];
        if (Schema::connection($connection)->hasColumn('deed_registrations', 'batch_session_id')) {
            $dropColumns[] = 'batch_session_id';
        }
        if (Schema::connection($connection)->hasColumn('deed_registrations', 'batch_printed_at')) {
            $dropColumns[] = 'batch_printed_at';
        }
        if (Schema::connection($connection)->hasColumn('deed_registrations', 'batch_printed_by')) {
            $dropColumns[] = 'batch_printed_by';
        }

        if (!empty($dropColumns)) {
            Schema::connection($connection)->table('deed_registrations', function (Blueprint $table) use ($dropColumns) {
                $table->dropColumn($dropColumns);
            });
        }
    }
};