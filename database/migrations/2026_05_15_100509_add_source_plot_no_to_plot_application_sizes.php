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
        Schema::table('plot_application_sizes', function (Blueprint $table) {
            $table->string('source_file_no', 100)->nullable()->after('plot_number');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('plot_application_sizes', function (Blueprint $table) {
            $table->dropColumn('source_file_no');
        });
    }
};
