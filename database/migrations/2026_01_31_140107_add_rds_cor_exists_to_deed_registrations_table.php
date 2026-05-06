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
        Schema::connection('sqlsrv')->table('deed_registrations', function (Blueprint $table) {
            $table->boolean('rds_exists')->default(false)->nullable();
            $table->boolean('cor_exists')->default(false)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->table('deed_registrations', function (Blueprint $table) {
            $table->dropColumn(['rds_exists', 'cor_exists']);
        });
    }
};
