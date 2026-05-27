<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up()
    {
        Schema::connection('sqlsrv')->table('land_recommendations', function (Blueprint $table) {
            $table->smallInteger('cofo_year')->nullable()->after('term');
        });
    }

    public function down()
    {
        Schema::connection('sqlsrv')->table('land_recommendations', function (Blueprint $table) {
            $table->dropColumn('cofo_year');
        });
    }
};
