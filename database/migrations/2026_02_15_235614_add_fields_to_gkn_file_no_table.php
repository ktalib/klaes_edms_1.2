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
            if (!Schema::connection('sqlsrv')->hasColumn('gkn_file_no', 'tp_no')) {
                $table->string('tp_no', 100)->nullable()->after('plot_no');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('gkn_file_no', 'commissioning_time')) {
                $table->time('commissioning_time')->nullable()->after('commissioning_date');
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
        Schema::connection('sqlsrv')->table('gkn_file_no', function (Blueprint $table) {
            $table->dropColumn(['tp_no', 'commissioning_time']);
        });
    }
};
