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
        Schema::connection('sqlsrv')->table('valuation_compensations', function (Blueprint $table) {
            $table->string('structure_type')->nullable()->after('building_type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->table('valuation_compensations', function (Blueprint $table) {
            $table->dropColumn('structure_type');
        });
    }
};
