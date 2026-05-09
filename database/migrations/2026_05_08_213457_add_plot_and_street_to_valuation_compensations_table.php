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
            $table->string('plot_no')->nullable()->after('owner_name');
            $table->string('street_name')->nullable()->after('plot_no');
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
            $table->dropColumn(['plot_no', 'street_name']);
        });
    }
};
