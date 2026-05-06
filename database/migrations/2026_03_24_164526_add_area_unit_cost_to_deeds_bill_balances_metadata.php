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
        Schema::connection('sqlsrv')->table('deeds_bill_balances_metadata', function (Blueprint $table) {
            $table->decimal('area', 18, 2)->nullable()->after('district');
            $table->decimal('unit_cost', 18, 2)->nullable()->after('area');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->table('deeds_bill_balances_metadata', function (Blueprint $table) {
            $table->dropColumn(['area', 'unit_cost']);
        });
    }
};
