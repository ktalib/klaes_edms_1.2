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
        Schema::connection('sqlsrv')->table('file_indexings', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('file_indexings', 'complainant')) {
                $table->string('complainant', 255)->nullable()->after('file_title');
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
        Schema::connection('sqlsrv')->table('file_indexings', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('file_indexings', 'complainant')) {
                $table->dropColumn('complainant');
            }
        });
    }
};
