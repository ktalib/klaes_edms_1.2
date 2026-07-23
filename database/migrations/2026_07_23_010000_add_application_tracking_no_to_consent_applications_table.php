<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds a human-readable tracking number (CONS-YYYY-NNNN) and backfills
     * existing rows so none are left without one.
     *
     * @return void
     */
    public function up()
    {
        // consent_applications lives on sqlsrv (see ConsentApplication::$connection).
        $connection = Schema::connection('sqlsrv');
        $db = DB::connection('sqlsrv');

        $connection->table('consent_applications', function (Blueprint $table) {
            $table->string('application_tracking_no', 40)->nullable();
        });

        // Backfill oldest-first so the sequence follows the order records were created.
        $counters = [];
        $rows = $db->table('consent_applications')
            ->select('id', 'created_at')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $year = $row->created_at ? date('Y', strtotime($row->created_at)) : date('Y');
            $counters[$year] = ($counters[$year] ?? 0) + 1;

            $db->table('consent_applications')
                ->where('id', $row->id)
                ->update([
                    'application_tracking_no' => 'CONS-' . $year . '-' . str_pad((string) $counters[$year], 4, '0', STR_PAD_LEFT),
                ]);
        }

        // Filtered index: SQL Server treats multiple NULLs as duplicates under a
        // plain unique index, so exclude them explicitly.
        $db->statement('
            CREATE UNIQUE NONCLUSTERED INDEX ux_consent_applications_tracking_no
            ON consent_applications(application_tracking_no)
            WHERE application_tracking_no IS NOT NULL
        ');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::connection('sqlsrv')->statement('
            DROP INDEX IF EXISTS ux_consent_applications_tracking_no ON consent_applications
        ');

        Schema::connection('sqlsrv')->table('consent_applications', function (Blueprint $table) {
            $table->dropColumn('application_tracking_no');
        });
    }
};
