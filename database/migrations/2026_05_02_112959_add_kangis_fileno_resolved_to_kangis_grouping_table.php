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
        Schema::connection('sqlsrv')->table('kangis_grouping', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('kangis_grouping', 'kangis_fileno_resolved')) {
                $table->string('kangis_fileno_resolved', 255)->nullable();
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
        Schema::connection('sqlsrv')->table('kangis_grouping', function (Blueprint $table) {
            $table->dropColumn('kangis_fileno_resolved');
        });
    }
};
