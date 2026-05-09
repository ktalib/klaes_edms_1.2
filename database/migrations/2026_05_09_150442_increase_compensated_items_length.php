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
            $table->text('compensated_items')->nullable()->change();
            $table->text('compensated_items_other')->nullable()->change();
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
            $table->string('compensated_items', 255)->nullable()->change();
            $table->string('compensated_items_other', 255)->nullable()->change();
        });
    }
};
