<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection('sqlsrv')->table('grouping', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('grouping', 'cadastral_tracking_id')) {
                $table->string('cadastral_tracking_id', 255)->nullable()
                      ->comment('tracking_id with a -0001, -0002, ... suffix; used as the cadastral QR value');
            }
        });

        // Backfill: <tracking_id>-0001, <tracking_id>-0002, ... numbered per tracking_id.
        DB::connection('sqlsrv')->statement(<<<'SQL'
;WITH numbered AS (
    SELECT id,
           tracking_id,
           ROW_NUMBER() OVER (PARTITION BY tracking_id ORDER BY id) AS rn
    FROM grouping
    WHERE tracking_id IS NOT NULL
      AND LTRIM(RTRIM(tracking_id)) <> ''
)
UPDATE g
SET g.cadastral_tracking_id = n.tracking_id + '-' + RIGHT('0000' + CAST(n.rn AS VARCHAR(10)), 4)
FROM grouping g
INNER JOIN numbered n ON n.id = g.id;
SQL);
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('grouping', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('grouping', 'cadastral_tracking_id')) {
                $table->dropColumn('cadastral_tracking_id');
            }
        });
    }
};
