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
        Schema::connection('sqlsrv')->table('file_indexings', function (Blueprint $table) {
            $table->string('file_type', 20)->nullable()->after('lga'); // Corporate or Individual
            $table->date('dob')->nullable()->after('file_type'); // Date of Birth
            $table->string('nin', 13)->nullable()->after('dob'); // National Identification Number (13 digits)
            $table->string('tin', 50)->nullable()->after('nin'); // Tax Identification Number
            $table->string('rc_no', 50)->nullable()->after('tin'); // Registration Certificate Number (for corporate)
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
            $table->dropColumn(['file_type', 'dob', 'nin', 'tin', 'rc_no']);
        });
    }
};
