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
        Schema::table('instrument_capture', function (Blueprint $table) {
            if (!Schema::hasColumn('instrument_capture', 'party_1_district')) {
                $table->string('party_1_district')->nullable()->after('party_1_lga');
            }
            if (!Schema::hasColumn('instrument_capture', 'party_2_district')) {
                $table->string('party_2_district')->nullable()->after('party_2_lga');
            }
            if (!Schema::hasColumn('instrument_capture', 'party_3_district')) {
                $table->string('party_3_district')->nullable()->after('party_3_lga');
            }
            if (!Schema::hasColumn('instrument_capture', 'solicitor_district')) {
                $table->string('solicitor_district')->nullable()->after('solicitor_name');
            }
            // 'district' column is also mentioned in the error: [lga], [district], [land_use]
            // Let's check where it fits best, usually after lga
            if (!Schema::hasColumn('instrument_capture', 'district')) {
                $table->string('district')->nullable()->after('lga');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('instrument_capture', function (Blueprint $table) {
            $table->dropColumn([
                'party_1_district',
                'party_2_district',
                'party_3_district',
                'solicitor_district',
                'district',
            ]);
        });
    }
};
