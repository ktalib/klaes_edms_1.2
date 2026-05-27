<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        DB::connection('sqlsrv')->statement("ALTER TABLE land_recommendations ADD num_plots nvarchar(100) NULL");
        DB::connection('sqlsrv')->statement("ALTER TABLE land_recommendations ADD file_title nvarchar(500) NULL");
        DB::connection('sqlsrv')->statement("ALTER TABLE land_recommendations ADD premium_words nvarchar(500) NULL");
        DB::connection('sqlsrv')->statement("ALTER TABLE land_recommendations ADD preparation_fees_words nvarchar(500) NULL");
        DB::connection('sqlsrv')->statement("ALTER TABLE land_recommendations ADD plot_sizes nvarchar(max) NULL");
    }

    public function down()
    {
        DB::connection('sqlsrv')->statement("ALTER TABLE land_recommendations DROP COLUMN plot_sizes");
        DB::connection('sqlsrv')->statement("ALTER TABLE land_recommendations DROP COLUMN preparation_fees_words");
        DB::connection('sqlsrv')->statement("ALTER TABLE land_recommendations DROP COLUMN premium_words");
        DB::connection('sqlsrv')->statement("ALTER TABLE land_recommendations DROP COLUMN file_title");
        DB::connection('sqlsrv')->statement("ALTER TABLE land_recommendations DROP COLUMN num_plots");
    }
};
