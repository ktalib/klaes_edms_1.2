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
        Schema::table('subapplications', function (Blueprint $table) {
            if (!Schema::hasColumn('subapplications', 'improvement_value')) {
                // Using string to allow flexible input like "To Follow" or formatted currency
                $table->string('improvement_value')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('subapplications', function (Blueprint $table) {
            if (Schema::hasColumn('subapplications', 'improvement_value')) {
                $table->dropColumn('improvement_value');
            }
        });
    }
};
