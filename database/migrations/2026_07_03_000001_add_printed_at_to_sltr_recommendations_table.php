<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('sqlsrv')->statement(
            "ALTER TABLE sltr_recommendations ADD printed_at datetime2 NULL"
        );
    }

    public function down(): void
    {
        DB::connection('sqlsrv')->statement(
            "ALTER TABLE sltr_recommendations DROP COLUMN printed_at"
        );
    }
};
