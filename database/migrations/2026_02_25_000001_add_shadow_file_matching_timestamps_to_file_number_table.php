<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Shadow File Commissioning - Physical Planning Module
     * Add timestamp columns to fileNumber table for tracking matches
     * from Lands, ST, and SLTR modules without creating shadow file records.
     * 
     * This migration supports the new matching-only architecture where
     * shadow file records are matched against fileNumber table and only
     * the fileNumber is updated with match timestamps.
     */
    public function up()
    {
        Schema::connection('sqlsrv')->table('fileNumber', function (Blueprint $table) {
            // Lands Module Matching Timestamps
            $table->date('pp_lands_date_matched')->nullable()->comment('Date when file was matched in Lands module');
            $table->time('pp_lands_time_matched')->nullable()->comment('Time when file was matched in Lands module');
            
            // ST Module Matching Timestamps  
            $table->date('pp_st_date_matched')->nullable()->comment('Date when file was matched in ST module');
            $table->time('pp_st_time_matched')->nullable()->comment('Time when file was matched in ST module');
            
            // SLTR Module Matching Timestamps
            $table->date('pp_sltr_date_matched')->nullable()->comment('Date when file was matched in SLTR module');
            $table->time('pp_sltr_time_matched')->nullable()->comment('Time when file was matched in SLTR module');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::connection('sqlsrv')->table('fileNumber', function (Blueprint $table) {
            $table->dropColumn([ 
                'pp_lands_date_matched',
                'pp_lands_time_matched', 
                'pp_st_date_matched',
                'pp_st_time_matched',
                'pp_sltr_date_matched',
                'pp_sltr_time_matched'
            ]);
        });
    }
};