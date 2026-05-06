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
        Schema::connection('sqlsrv')->table('purposes', function (Blueprint $table) {
            $table->dropForeign('FK_Purposes_Landuses');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Re-adding the foreign key might fail if data is inconsistent, so we skip it or handle carefully.
        // For now, leaving it empty as we are transitioning.
    }
};
