<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('sqlsrv')->statement(
            "ALTER TABLE sltr_recommendations ADD sltr_rofo_serial_no nvarchar(100) NULL"
        );
    }

    public function down(): void
    {
        DB::connection('sqlsrv')->statement(
            "ALTER TABLE sltr_recommendations DROP COLUMN sltr_rofo_serial_no"
        );
    }
};
