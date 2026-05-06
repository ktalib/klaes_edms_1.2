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
            if (!Schema::connection('sqlsrv')->hasColumn('gkn_file_no', 'batch_no')) {
                $table->string('batch_no', 100)->nullable()->after('id');
                $table->index('batch_no');
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
            $table->dropIndex(['batch_no']);
            $table->dropColumn(['batch_no']);
        });
    }
};
