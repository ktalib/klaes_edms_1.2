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
        Schema::connection('sqlsrv')->table('fileNumber', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('fileNumber', 'phone_no')) {
                $table->string('phone_no', 100)->nullable();
            }
        });

        Schema::connection('sqlsrv')->table('mls_file_no', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('mls_file_no', 'phone_no')) {
                $table->string('phone_no', 100)->nullable();
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
        Schema::connection('sqlsrv')->table('fileNumber', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('fileNumber', 'phone_no')) {
                $table->dropColumn('phone_no');
            }
        });

        Schema::connection('sqlsrv')->table('mls_file_no', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('mls_file_no', 'phone_no')) {
                $table->dropColumn('phone_no');
            }
        });
    }
};
