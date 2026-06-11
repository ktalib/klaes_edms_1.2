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
        if (!Schema::connection('sqlsrv')->hasColumn('phs_institutions', 'low_balance_notified_at')) {
            Schema::connection('sqlsrv')->table('phs_institutions', function (Blueprint $table) {
                $table->dateTime('low_balance_notified_at')->nullable()->after('status');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::connection('sqlsrv')->hasColumn('phs_institutions', 'low_balance_notified_at')) {
            Schema::connection('sqlsrv')->table('phs_institutions', function (Blueprint $table) {
                $table->dropColumn('low_balance_notified_at');
            });
        }
    }
};
