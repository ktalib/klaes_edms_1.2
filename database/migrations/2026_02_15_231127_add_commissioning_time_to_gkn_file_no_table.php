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
        Schema::connection('sqlsrv')->table('gkn_file_no', function (Blueprint $table) {
            $table->time('commissioning_time')->nullable()->after('commissioning_date');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->table('gkn_file_no', function (Blueprint $table) {
            $table->dropColumn('commissioning_time');
        });
    }
};
