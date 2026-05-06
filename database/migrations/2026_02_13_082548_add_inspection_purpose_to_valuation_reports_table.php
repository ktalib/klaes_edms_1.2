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
        Schema::table('valuation_reports', function (Blueprint $table) {
            $table->string('inspection_purpose')->nullable()->after('valuation_purpose');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('valuation_reports', function (Blueprint $table) {
            $table->dropColumn('inspection_purpose');
        });
    }
};
