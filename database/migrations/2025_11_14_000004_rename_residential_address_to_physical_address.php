<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("EXEC sp_rename 'customers.residential_address', 'physical_address', 'COLUMN'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("EXEC sp_rename 'customers.physical_address', 'residential_address', 'COLUMN'");
    }
};
