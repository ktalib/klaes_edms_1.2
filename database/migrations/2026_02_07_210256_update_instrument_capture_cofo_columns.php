<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('sqlsrv')->table('instrument_capture', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('instrument_capture', 'cofo_type')) {
                $table->string('cofo_type')->nullable()->after('instrument_type');
            }
            if (Schema::connection('sqlsrv')->hasColumn('instrument_capture', 'coo_recertification')) {
                $table->dropColumn('coo_recertification');
            }
            if (Schema::connection('sqlsrv')->hasColumn('instrument_capture', 'coo_conversion')) {
                $table->dropColumn('coo_conversion');
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
        Schema::connection('sqlsrv')->table('instrument_capture', function (Blueprint $table) {
            $table->dropColumn('cofo_type');
            $table->boolean('coo_recertification')->default(false);
            $table->boolean('coo_conversion')->default(false);
        });
    }
};
