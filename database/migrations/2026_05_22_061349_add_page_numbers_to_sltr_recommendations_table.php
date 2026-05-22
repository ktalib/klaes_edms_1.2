<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection('sqlsrv')->table('sltr_recommendations', function (Blueprint $table) {
            $table->unsignedSmallInteger('page_application')->nullable()->default(1)->after('plot_number');
            $table->unsignedSmallInteger('page_survey')->nullable()->default(9)->after('page_application');
            $table->unsignedSmallInteger('page_planning')->nullable()->default(17)->after('page_survey');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('sltr_recommendations', function (Blueprint $table) {
            $table->dropColumn(['page_application', 'page_survey', 'page_planning']);
        });
    }
};
