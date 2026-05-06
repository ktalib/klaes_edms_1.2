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
        Schema::table('consent_applications', function (Blueprint $table) {
            $table->string('property_description')->nullable();
            $table->json('additional_applicants')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('consent_applications', function (Blueprint $table) {
            $table->dropColumn(['property_description', 'additional_applicants']);
        });
    }
};
