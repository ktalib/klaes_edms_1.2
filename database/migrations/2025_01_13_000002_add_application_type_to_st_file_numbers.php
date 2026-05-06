<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddApplicationTypeToStFileNumbers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::connection('sqlsrv')->hasColumn('st_file_numbers', 'application_type')) {
            Schema::connection('sqlsrv')->table('st_file_numbers', function (Blueprint $table) {
                // Add application_type column for PRIMARY, PUA, and SUA file numbers
                // Options: Direct Allocation, Conversion, Primary (for PuA inherited from parent)
                $table->string('application_type', 50)->nullable()->after('file_no_type');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->table('st_file_numbers', function (Blueprint $table) {
            $table->dropColumn('application_type');
        });
    }
}
