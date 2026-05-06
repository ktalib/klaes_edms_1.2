<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('sqlsrv')->table('deed_registrations', function (Blueprint $table) {
            $table->unsignedBigInteger('instrument_capture_id')->nullable()->after('id');
            $table->index('instrument_capture_id');
        });

        // Data Repair: Link existing records based on registration_number (particulars)
        // This handles "ones in production" as requested.
        DB::connection('sqlsrv')->statement("
            UPDATE dr
            SET dr.instrument_capture_id = ic.id
            FROM deed_registrations dr
            JOIN instrument_capture ic ON dr.registration_number = ic.registration_number
            WHERE dr.instrument_capture_id IS NULL
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->table('deed_registrations', function (Blueprint $table) {
            $table->dropColumn('instrument_capture_id');
        });
    }
};
