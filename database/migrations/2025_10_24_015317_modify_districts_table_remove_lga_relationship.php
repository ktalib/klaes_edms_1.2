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
        Schema::connection('sqlsrv')->table('districts', function (Blueprint $table) {
            // Drop the foreign key constraint first
            $table->dropForeign(['lga_id']);
            // Drop the lga_id column since districts are now independent
            $table->dropColumn('lga_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->table('districts', function (Blueprint $table) {
            // Add back the lga_id column
            $table->unsignedBigInteger('lga_id')->nullable();
            // Re-add the foreign key constraint
            $table->foreign('lga_id')->references('id')->on('lgas')->onDelete('cascade');
        });
    }
};
