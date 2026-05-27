<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        DB::connection('sqlsrv')->statement(
            "EXEC sp_rename 'land_recommendations.application_types', 'application_type', 'COLUMN'"
        );
    }

    public function down()
    {
        DB::connection('sqlsrv')->statement(
            "EXEC sp_rename 'land_recommendations.application_type', 'application_types', 'COLUMN'"
        );
    }
};
