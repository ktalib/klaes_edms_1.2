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
            if (!Schema::connection('sqlsrv')->hasColumn('deeds_bill_balances_metadata', 'rent_from_year')) {
                $table->integer('rent_from_year')->nullable()->after('date_of_expiry');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('deeds_bill_balances_metadata', 'rent_to_year')) {
                $table->integer('rent_to_year')->nullable()->after('rent_from_year');
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
        Schema::connection('sqlsrv')->table('deeds_bill_balances_metadata', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('deeds_bill_balances_metadata', 'rent_to_year')) {
                $table->dropColumn('rent_to_year');
            }
            if (Schema::connection('sqlsrv')->hasColumn('deeds_bill_balances_metadata', 'rent_from_year')) {
                $table->dropColumn('rent_from_year');
            }
        });
    }
};
