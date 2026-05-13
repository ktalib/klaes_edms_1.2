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
        if (Schema::connection('sqlsrv')->hasTable('fileNumber')) {
            Schema::connection('sqlsrv')->table('fileNumber', function (Blueprint $table) {
                if (!Schema::connection('sqlsrv')->hasColumn('fileNumber', 'parent_prop_id')) {
                    $table->string('parent_prop_id', 50)->nullable();
                }
            });
        }

        if (Schema::connection('sqlsrv')->hasTable('file_indexings')) {
            Schema::connection('sqlsrv')->table('file_indexings', function (Blueprint $table) {
                if (!Schema::connection('sqlsrv')->hasColumn('file_indexings', 'parent_prop_id')) {
                    $table->string('parent_prop_id', 50)->nullable();
                }
            });
        }
    }

    public function down()
    {
        if (Schema::connection('sqlsrv')->hasTable('fileNumber')) {
            Schema::connection('sqlsrv')->table('fileNumber', function (Blueprint $table) {
                $table->dropColumn('parent_prop_id');
            });
        }

        if (Schema::connection('sqlsrv')->hasTable('file_indexings')) {
            Schema::connection('sqlsrv')->table('file_indexings', function (Blueprint $table) {
                $table->dropColumn('parent_prop_id');
            });
        }
    }
};
