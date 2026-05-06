<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $conn = Schema::connection('sqlsrv');

        // Rename land_officers → signing_officers (if not already renamed)
        if ($conn->hasTable('land_officers') && !$conn->hasTable('signing_officers')) {
            DB::connection('sqlsrv')->statement('EXEC sp_rename \'land_officers\', \'signing_officers\'');
        }
    }

    public function down(): void
    {
        $conn = Schema::connection('sqlsrv');

        if ($conn->hasTable('signing_officers') && !$conn->hasTable('land_officers')) {
            DB::connection('sqlsrv')->statement('EXEC sp_rename \'signing_officers\', \'land_officers\'');
        }
    }
};
