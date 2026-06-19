<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * InstrumentCaptureService writes party_1/2 house & plot numbers, but these
 * columns were missing on instrument_capture in some environments, causing
 * "Invalid column name 'party_1_house_no'" on capture. Add them to match the
 * party address fields already present (party_X_city/state/lga/district).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('instrument_capture', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('instrument_capture', 'party_1_house_no')) {
                $table->string('party_1_house_no', 100)->nullable();
            }
            if (!Schema::connection('sqlsrv')->hasColumn('instrument_capture', 'party_1_plot_no')) {
                $table->string('party_1_plot_no', 100)->nullable();
            }
            if (!Schema::connection('sqlsrv')->hasColumn('instrument_capture', 'party_2_house_no')) {
                $table->string('party_2_house_no', 100)->nullable();
            }
            if (!Schema::connection('sqlsrv')->hasColumn('instrument_capture', 'party_2_plot_no')) {
                $table->string('party_2_plot_no', 100)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('instrument_capture', function (Blueprint $table) {
            foreach (['party_1_house_no', 'party_1_plot_no', 'party_2_house_no', 'party_2_plot_no'] as $col) {
                if (Schema::connection('sqlsrv')->hasColumn('instrument_capture', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
