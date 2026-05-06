<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add indices to grouping tables for faster file number lookups
        $tables = [
            'gkn_grouping' => 'gkn_awaiting_fileno',
            'sltr_grouping' => 'sltr_awaiting_fileno',
            'sit_grouping' => 'sit_awaiting_fileno',
            'dciv_grouping' => 'dciv_awaiting_fileno',
        ];

        foreach ($tables as $table => $column) {
            try {
                Schema::connection('sqlsrv')->table($table, function (Blueprint $tableObj) use ($column, $table) {
                    $indexName = "IX_{$table}_{$column}";
                    // Check if index exists before creating
                    $exists = DB::connection('sqlsrv')->select("
                        SELECT 1 FROM sys.indexes 
                        WHERE name = ? AND object_id = OBJECT_ID(?)
                    ", [$indexName, $table]);

                    if (empty($exists)) {
                        $tableObj->index($column, $indexName);
                    }
                });
            } catch (\Exception $e) {
                // Log and continue if table doesn't exist or column is missing
                \Log::warning("Could not add index to {$table}.{$column}: " . $e->getMessage());
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'gkn_grouping' => 'gkn_awaiting_fileno',
            'sltr_grouping' => 'sltr_awaiting_fileno',
            'sit_grouping' => 'sit_awaiting_fileno',
            'dciv_grouping' => 'dciv_awaiting_fileno',
        ];

        foreach ($tables as $table => $column) {
            try {
                Schema::connection('sqlsrv')->table($table, function (Blueprint $tableObj) use ($column, $table) {
                    $tableObj->dropIndex("IX_{$table}_{$column}");
                });
            } catch (\Exception $e) {
                // Silently ignore failures on rollback
            }
        }
    }
};
