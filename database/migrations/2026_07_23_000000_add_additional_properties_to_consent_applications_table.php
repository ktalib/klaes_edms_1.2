<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Each entry pairs an extra file number with its own property description,
     * mirroring the shape of additional_parties / additional_applicants.
     *
     * @return void
     */
    public function up()
    {
        // consent_applications lives on sqlsrv (see ConsentApplication::$connection),
        // not on the default connection.
        Schema::connection('sqlsrv')->table('consent_applications', function (Blueprint $table) {
            $table->json('additional_properties')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->table('consent_applications', function (Blueprint $table) {
            $table->dropColumn('additional_properties');
        });
    }
};
