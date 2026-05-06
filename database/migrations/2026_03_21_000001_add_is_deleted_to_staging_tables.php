<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add is_deleted column to the 4 legal search staging tables
     * for soft-delete (Remove) functionality in Cleanup Mode.
     */
    public function up(): void
    {
        $tables = ['file_history_staging', 'CofO_staging', 'pra', 'deed_registrations'];

        foreach ($tables as $table) {
            Schema::connection('sqlsrv')->table($table, function (Blueprint $blueprint) use ($table) {
                if (!Schema::connection('sqlsrv')->hasColumn($table, 'is_deleted')) {
                    $blueprint->boolean('is_deleted')->default(0)->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = ['file_history_staging', 'CofO_staging', 'pra', 'deed_registrations'];

        foreach ($tables as $table) {
            Schema::connection('sqlsrv')->table($table, function (Blueprint $blueprint) use ($table) {
                if (Schema::connection('sqlsrv')->hasColumn($table, 'is_deleted')) {
                    $blueprint->dropColumn('is_deleted');
                }
            });
        }
    }
};
