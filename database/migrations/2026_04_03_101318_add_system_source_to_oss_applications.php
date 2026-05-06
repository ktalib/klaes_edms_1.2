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
        Schema::connection('sqlsrv')->table('oss_applications', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('oss_applications', 'system_source')) {
                $table->string('system_source', 50)->nullable()->after('status')->index();
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
        Schema::connection('sqlsrv')->table('oss_applications', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('oss_applications', 'system_source')) {
                $table->dropColumn('system_source');
            }
        });
    }
};
