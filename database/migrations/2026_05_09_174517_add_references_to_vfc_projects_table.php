<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::connection('sqlsrv')->table('vfc_projects', function (Blueprint $table) {
            $table->string('our_reference')->nullable();
            $table->string('your_reference')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->table('vfc_projects', function (Blueprint $table) {
            $table->dropColumn(['our_reference', 'your_reference']);
        });
    }
};
