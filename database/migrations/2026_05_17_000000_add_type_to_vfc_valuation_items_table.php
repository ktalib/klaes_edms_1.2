<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 1. Ensure type column exists on vfc_valuation_items
        if (!Schema::connection('sqlsrv')->hasColumn('vfc_valuation_items', 'type')) {
            Schema::connection('sqlsrv')->table('vfc_valuation_items', function (Blueprint $table) {
                $table->string('type')->nullable()->default('Other');
            });
        }

        // 2. Insert or update default items with correct types
        $structureTypes = [
            'Permanent',
            'Semi-Permanent',
            'Permanent GF/FF',
            'Warehouse',
            'Semi-Permanent GF/FF',
            'Proposed Building'
        ];

        $completionStages = [
            'Building',
            'Complete',
            'Liatel',
            'Roof Level'
        ];

        // Seed/Update Structure Types
        foreach ($structureTypes as $name) {
            DB::connection('sqlsrv')->table('vfc_valuation_items')->updateOrInsert(
                ['name' => $name],
                ['type' => 'StructureType', 'is_active' => true, 'updated_at' => now()]
            );
        }

        // Seed/Update Completion Stages
        foreach ($completionStages as $name) {
            DB::connection('sqlsrv')->table('vfc_valuation_items')->updateOrInsert(
                ['name' => $name],
                ['type' => 'CompletionStage', 'is_active' => true, 'updated_at' => now()]
            );
        }

        // Make sure "Other" entries exist for all three types
        // 1. StructureType Other
        DB::connection('sqlsrv')->table('vfc_valuation_items')->updateOrInsert(
            ['name' => 'Other', 'type' => 'StructureType'],
            ['is_active' => true, 'updated_at' => now()]
        );

        // 2. CompletionStage Other
        DB::connection('sqlsrv')->table('vfc_valuation_items')->updateOrInsert(
            ['name' => 'Other', 'type' => 'CompletionStage'],
            ['is_active' => true, 'updated_at' => now()]
        );

        // 3. General Other
        DB::connection('sqlsrv')->table('vfc_valuation_items')->updateOrInsert(
            ['name' => 'Other', 'type' => 'Other'],
            ['is_active' => true, 'updated_at' => now()]
        );

        // Update all other existing entries to have type 'Other' if it's currently null
        DB::connection('sqlsrv')->table('vfc_valuation_items')
            ->whereNull('type')
            ->update(['type' => 'Other']);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::connection('sqlsrv')->hasColumn('vfc_valuation_items', 'type')) {
            Schema::connection('sqlsrv')->table('vfc_valuation_items', function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }
    }
};
