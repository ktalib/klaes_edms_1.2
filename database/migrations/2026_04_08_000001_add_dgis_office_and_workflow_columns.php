<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add "Director GIS" office if it doesn't exist
        $exists = DB::connection('sqlsrv')
            ->table('offices')
            ->where('office_code', 'DGIS')
            ->exists();

        if (!$exists) {
            DB::connection('sqlsrv')->table('offices')->insert([
                'office_code' => 'DGIS',
                'office_name' => 'Director GIS',
                'office_abbreviation' => 'DGIS',
                'department' => 'Management',
                'is_active' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }

        // 2. Add workflow columns to file_tracker table
        Schema::connection('sqlsrv')->table('file_tracker', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('file_tracker', 'workflow_type')) {
                $table->string('workflow_type', 50)->nullable()->after('module');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('file_tracker', 'workflow_step')) {
                $table->tinyInteger('workflow_step')->nullable()->after('workflow_type');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('file_tracker', 'workflow_config')) {
                $table->text('workflow_config')->nullable()->after('workflow_step');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('file_tracker', function (Blueprint $table) {
            $columns = ['workflow_type', 'workflow_step', 'workflow_config'];
            foreach ($columns as $col) {
                if (Schema::connection('sqlsrv')->hasColumn('file_tracker', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        DB::connection('sqlsrv')
            ->table('offices')
            ->where('office_code', 'DGIS')
            ->delete();
    }
};
