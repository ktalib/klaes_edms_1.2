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
        Schema::table('consent_applications', function (Blueprint $table) {
            $table->string('c_of_o_no')->nullable()->after('file_number');
            $table->string('consideration_words')->nullable()->after('consideration');
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
            $table->dropColumn(['c_of_o_no', 'consideration_words']);
        });
    }
};
