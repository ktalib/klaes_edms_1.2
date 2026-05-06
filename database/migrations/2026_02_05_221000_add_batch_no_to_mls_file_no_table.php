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
        Schema::connection('sqlsrv')->table('mls_file_no', function (Blueprint $table) {
            $table->string('batch_no', 50)->nullable()->index()->comment('Batch identifier for grouped file commissioning');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->table('mls_file_no', function (Blueprint $table) {
            $table->dropColumn('batch_no');
        });
    }
};
