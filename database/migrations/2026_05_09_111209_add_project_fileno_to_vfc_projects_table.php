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
        Schema::connection('sqlsrv')->table('vfc_projects', function (Blueprint $table) {
            $table->string('project_fileno')->nullable();
        });
    }

    public function down()
    {
        Schema::connection('sqlsrv')->table('vfc_projects', function (Blueprint $table) {
            $table->dropColumn('project_fileno');
        });
    }
};
