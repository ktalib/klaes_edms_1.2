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
        Schema::table('rofo', function (Blueprint $table) {
            // Modify columns to allow text storage for non-numeric fee descriptions
            $table->string('recertification_fee')->nullable()->change();
            $table->string('development_charges')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('rofo', function (Blueprint $table) {
            // Revert back to numeric/decimal if needed (assuming originally decimal or null)
            // Note: Reversing might lose text data if not handled carefully, 
            // but for this task we assume we want to stick with text.
            $table->decimal('recertification_fee', 15, 2)->nullable()->change();
            $table->decimal('development_charges', 15, 2)->nullable()->change();
        });
    }
};
