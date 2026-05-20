<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('instrument_capture', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('instrument_capture', 'party_1_house_no')) {
                $table->string('party_1_house_no')->nullable()->after('party_1_name');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('instrument_capture', 'party_1_plot_no')) {
                $table->string('party_1_plot_no')->nullable()->after('party_1_house_no');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('instrument_capture', 'party_2_house_no')) {
                $table->string('party_2_house_no')->nullable()->after('party_2_name');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('instrument_capture', 'party_2_plot_no')) {
                $table->string('party_2_plot_no')->nullable()->after('party_2_house_no');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('instrument_capture', function (Blueprint $table) {
            $table->dropColumn([
                'party_1_house_no',
                'party_1_plot_no',
                'party_2_house_no',
                'party_2_plot_no',
            ]);
        });
    }
};
