<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('instrument_capture', function (Blueprint $table) {
            // Party 1 address components
            $table->string('party_1_state')->nullable();
            $table->string('party_1_lga')->nullable();
            $table->string('party_1_city')->nullable();

            // Party 2 address components
            $table->string('party_2_state')->nullable();
            $table->string('party_2_lga')->nullable();
            $table->string('party_2_city')->nullable();

            // Party 3 address components
            $table->string('party_3_state')->nullable();
            $table->string('party_3_lga')->nullable();
            $table->string('party_3_city')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('sqlsrv')->table('instrument_capture', function (Blueprint $table) {
            $table->dropColumn([
                'party_1_state',
                'party_1_lga',
                'party_1_city',
                'party_2_state',
                'party_2_lga',
                'party_2_city',
                'party_3_state',
                'party_3_lga',
                'party_3_city',
            ]);
        });
    }
};
