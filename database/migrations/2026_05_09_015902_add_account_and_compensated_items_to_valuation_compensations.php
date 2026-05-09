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
            $table->string('compensated_items')->nullable();
            $table->string('compensated_items_other')->nullable();
            $table->string('account_name')->nullable();
            $table->string('bank_name')->nullable();
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
            $table->dropColumn(['compensated_items', 'compensated_items_other', 'account_name', 'bank_name']);
        });
    }
};
