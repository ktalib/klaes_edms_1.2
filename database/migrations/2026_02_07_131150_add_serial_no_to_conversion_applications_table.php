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
        Schema::connection('sqlsrv')->table('conversion_applications', function (Blueprint $table) {
            $table->integer('serial_no')->nullable()->after('mls_file_no_id')->index();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->table('conversion_applications', function (Blueprint $table) {
            $table->dropColumn('serial_no');
        });
    }
};
