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
            $table->string('type')->default('Direct')->after('file_number');
            $table->string('page')->nullable()->after('applicant_address');
            $table->string('page_survey_report')->nullable()->after('page');
            $table->string('survey_report')->nullable()->after('page_survey_report');
            $table->string('improvement')->nullable()->after('survey_report');
            $table->string('revision_period')->nullable()->after('improvement');
            $table->string('time_of_erection')->nullable()->after('revision_period');
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
                'type',
                'page',
                'page_survey_report',
                'survey_report',
                'improvement',
                'revision_period',
                'time_of_erection'
            ]);
        });
    }
};
