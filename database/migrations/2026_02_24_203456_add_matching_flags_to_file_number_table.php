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
        Schema::connection('sqlsrv')->table('fileNumber', function (Blueprint $table) {
            $table->integer('pp_lands_matching')->default(0);
            $table->integer('pp_st_matching')->default(0);
            $table->integer('pp_sltr_matching')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->table('fileNumber', function (Blueprint $table) {
            $table->dropColumn(['pp_lands_matching', 'pp_st_matching', 'pp_sltr_matching']);
        });
    }
};
