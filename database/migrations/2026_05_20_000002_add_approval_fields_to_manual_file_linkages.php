<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('manual_file_linkages')) {
            return;
        }

        Schema::connection('sqlsrv')->table('manual_file_linkages', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('manual_file_linkages', 'approval_reference')) {
                $table->string('approval_reference', 255)->nullable();
            }
            if (!Schema::connection('sqlsrv')->hasColumn('manual_file_linkages', 'approval_date')) {
                $table->date('approval_date')->nullable();
            }
            if (!Schema::connection('sqlsrv')->hasColumn('manual_file_linkages', 'child_plot_number')) {
                $table->string('child_plot_number', 100)->nullable();
            }
            if (!Schema::connection('sqlsrv')->hasColumn('manual_file_linkages', 'child_plot_size')) {
                $table->string('child_plot_size', 100)->nullable();
            }
            if (!Schema::connection('sqlsrv')->hasColumn('manual_file_linkages', 'survey_plan_no')) {
                $table->string('survey_plan_no', 255)->nullable();
            }
            if (!Schema::connection('sqlsrv')->hasColumn('manual_file_linkages', 'linkage_group_id')) {
                // UUID grouping field: all Subdivision children from one submission share the same linkage_group_id
                $table->string('linkage_group_id', 36)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('manual_file_linkages')) {
            return;
        }

        Schema::connection('sqlsrv')->table('manual_file_linkages', function (Blueprint $table) {
            $cols = ['approval_reference', 'approval_date', 'child_plot_number', 'child_plot_size', 'survey_plan_no', 'linkage_group_id'];
            $existing = array_filter($cols, fn ($c) => Schema::connection('sqlsrv')->hasColumn('manual_file_linkages', $c));
            if ($existing) {
                $table->dropColumn(array_values($existing));
            }
        });
    }
};
