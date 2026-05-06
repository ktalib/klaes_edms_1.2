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
            if (!Schema::connection('sqlsrv')->hasColumn('fileNumber', 'address')) {
                $table->string('address', 255)->nullable();
            }
            if (!Schema::connection('sqlsrv')->hasColumn('fileNumber', 'rep_phone_no')) {
                $table->string('rep_phone_no', 100)->nullable();
            }
            if (!Schema::connection('sqlsrv')->hasColumn('fileNumber', 'rep_address')) {
                $table->string('rep_address', 255)->nullable();
            }
        });

        Schema::connection('sqlsrv')->table('mls_file_no', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('mls_file_no', 'address')) {
                $table->string('address', 255)->nullable();
            }
            if (!Schema::connection('sqlsrv')->hasColumn('mls_file_no', 'rep_phone_no')) {
                $table->string('rep_phone_no', 100)->nullable();
            }
            if (!Schema::connection('sqlsrv')->hasColumn('mls_file_no', 'rep_address')) {
                $table->string('rep_address', 255)->nullable();
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
            if (Schema::connection('sqlsrv')->hasColumn('fileNumber', 'address')) {
                $table->dropColumn('address');
            }
            if (Schema::connection('sqlsrv')->hasColumn('fileNumber', 'rep_phone_no')) {
                $table->dropColumn('rep_phone_no');
            }
            if (Schema::connection('sqlsrv')->hasColumn('fileNumber', 'rep_address')) {
                $table->dropColumn('rep_address');
            }
        });

        Schema::connection('sqlsrv')->table('mls_file_no', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('mls_file_no', 'address')) {
                $table->dropColumn('address');
            }
            if (Schema::connection('sqlsrv')->hasColumn('mls_file_no', 'rep_phone_no')) {
                $table->dropColumn('rep_phone_no');
            }
            if (Schema::connection('sqlsrv')->hasColumn('mls_file_no', 'rep_address')) {
                $table->dropColumn('rep_address');
            }
        });
    }
};
