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
        Schema::connection('sqlsrv')->table('land_recommendations', function (Blueprint $table) {
            $table->decimal('rofo_survey_fees', 18, 2)->nullable();
            $table->decimal('rofo_dev_charge', 18, 2)->nullable();
            $table->string('rofo_director_survey')->nullable(); // YES or NO
            $table->string('rofo_licensed_surveyor')->nullable(); // YES or NO
            $table->string('rofo_land_use_category')->nullable(); // For the table rows
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->table('land_recommendations', function (Blueprint $table) {
            $table->dropColumn([
                'rofo_survey_fees',
                'rofo_dev_charge',
                'rofo_director_survey',
                'rofo_licensed_surveyor',
                'rofo_land_use_category'
            ]);
        });
    }
};
