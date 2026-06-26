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
        Schema::connection('sqlsrv')->table('file_commissioning_sheets', function (Blueprint $table) {
            $table->string('related_file_number', 255)->nullable()->after('lga');
            $table->string('related_file_title', 255)->nullable()->after('related_file_number');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->table('file_commissioning_sheets', function (Blueprint $table) {
            $table->dropColumn(['related_file_number', 'related_file_title']);
        });
    }
};
