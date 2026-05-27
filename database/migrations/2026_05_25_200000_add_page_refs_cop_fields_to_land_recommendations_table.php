<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $conn = DB::connection('sqlsrv');
        $conn->statement("ALTER TABLE land_recommendations ADD page_2 nvarchar(50) NULL");
        $conn->statement("ALTER TABLE land_recommendations ADD page_3 nvarchar(50) NULL");
        $conn->statement("ALTER TABLE land_recommendations ADD page_4 nvarchar(50) NULL");
        $conn->statement("ALTER TABLE land_recommendations ADD page_5 nvarchar(50) NULL");
        $conn->statement("ALTER TABLE land_recommendations ADD purpose_description nvarchar(500) NULL");
        $conn->statement("ALTER TABLE land_recommendations ADD dimensions_text nvarchar(max) NULL");
    }

    public function down(): void
    {
        $conn = DB::connection('sqlsrv');
        foreach (['page_2','page_3','page_4','page_5','purpose_description','dimensions_text'] as $col) {
            $conn->statement("ALTER TABLE land_recommendations DROP COLUMN {$col}");
        }
    }
};
