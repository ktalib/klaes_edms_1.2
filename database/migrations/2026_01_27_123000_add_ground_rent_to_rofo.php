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
            // Add ground_rent column if it doesn't exist
            if (!Schema::hasColumn('rofo', 'ground_rent')) {
                $table->string('ground_rent')->nullable();
            } else {
                // Ensure it is string
                $table->string('ground_rent')->nullable()->change();
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
        // No strict reverse needed as we are fixing/adding
    }
};
