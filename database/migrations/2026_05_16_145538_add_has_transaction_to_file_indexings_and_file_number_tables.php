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
        if (Schema::connection('sqlsrv')->hasTable('file_indexings')) {
            Schema::connection('sqlsrv')->table('file_indexings', function (Blueprint $table) {
                if (!Schema::connection('sqlsrv')->hasColumn('file_indexings', 'has_transaction')) {
                    $table->boolean('has_transaction')->default(0)->after('has_temp_file');
                }
            });
        }

        if (Schema::connection('sqlsrv')->hasTable('fileNumber')) {
            Schema::connection('sqlsrv')->table('fileNumber', function (Blueprint $table) {
                if (!Schema::connection('sqlsrv')->hasColumn('fileNumber', 'has_transaction')) {
                    $table->boolean('has_transaction')->default(0)->after('has_temp_file');
                }
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
        if (Schema::connection('sqlsrv')->hasTable('file_indexings')) {
            Schema::connection('sqlsrv')->table('file_indexings', function (Blueprint $table) {
                if (Schema::connection('sqlsrv')->hasColumn('file_indexings', 'has_transaction')) {
                    $table->dropColumn('has_transaction');
                }
            });
        }

        if (Schema::connection('sqlsrv')->hasTable('fileNumber')) {
            Schema::connection('sqlsrv')->table('fileNumber', function (Blueprint $table) {
                if (Schema::connection('sqlsrv')->hasColumn('fileNumber', 'has_transaction')) {
                    $table->dropColumn('has_transaction');
                }
            });
        }
    }
};
