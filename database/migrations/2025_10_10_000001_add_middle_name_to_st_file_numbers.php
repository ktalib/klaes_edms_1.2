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
        Schema::connection('sqlsrv')->table('st_file_numbers', function (Blueprint $table) {
            $table->string('middle_name', 100)->nullable()->after('first_name')->comment('Middle name for individual applicants');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->table('st_file_numbers', function (Blueprint $table) {
            $table->dropColumn('middle_name');
        });
    }
};