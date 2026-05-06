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
        // Add action sheet columns to mother_applications table
        if (Schema::connection('sqlsrv')->hasTable('mother_applications')) {
            Schema::connection('sqlsrv')->table('mother_applications', function (Blueprint $table) {
                if (!Schema::connection('sqlsrv')->hasColumn('mother_applications', 'action_sheet_generated')) {
                    $table->string('action_sheet_generated', 10)->nullable()->after('comments');
                }
                if (!Schema::connection('sqlsrv')->hasColumn('mother_applications', 'action_sheet_generated_at')) {
                    $table->datetime('action_sheet_generated_at')->nullable()->after('action_sheet_generated');
                }
                if (!Schema::connection('sqlsrv')->hasColumn('mother_applications', 'action_sheet_generated_by')) {
                    $table->integer('action_sheet_generated_by')->nullable()->after('action_sheet_generated_at');
                }
            });
        }

        // Add action sheet columns to subapplications table
        if (Schema::connection('sqlsrv')->hasTable('subapplications')) {
            Schema::connection('sqlsrv')->table('subapplications', function (Blueprint $table) {
                if (!Schema::connection('sqlsrv')->hasColumn('subapplications', 'action_sheet_generated')) {
                    $table->string('action_sheet_generated', 10)->nullable()->after('comments');
                }
                if (!Schema::connection('sqlsrv')->hasColumn('subapplications', 'action_sheet_generated_at')) {
                    $table->datetime('action_sheet_generated_at')->nullable()->after('action_sheet_generated');
                }
                if (!Schema::connection('sqlsrv')->hasColumn('subapplications', 'action_sheet_generated_by')) {
                    $table->integer('action_sheet_generated_by')->nullable()->after('action_sheet_generated_at');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Remove action sheet columns from mother_applications table
        if (Schema::connection('sqlsrv')->hasTable('mother_applications')) {
            Schema::connection('sqlsrv')->table('mother_applications', function (Blueprint $table) {
                if (Schema::connection('sqlsrv')->hasColumn('mother_applications', 'action_sheet_generated')) {
                    $table->dropColumn('action_sheet_generated');
                }
                if (Schema::connection('sqlsrv')->hasColumn('mother_applications', 'action_sheet_generated_at')) {
                    $table->dropColumn('action_sheet_generated_at');
                }
                if (Schema::connection('sqlsrv')->hasColumn('mother_applications', 'action_sheet_generated_by')) {
                    $table->dropColumn('action_sheet_generated_by');
                }
            });
        }

        // Remove action sheet columns from subapplications table
        if (Schema::connection('sqlsrv')->hasTable('subapplications')) {
            Schema::connection('sqlsrv')->table('subapplications', function (Blueprint $table) {
                if (Schema::connection('sqlsrv')->hasColumn('subapplications', 'action_sheet_generated')) {
                    $table->dropColumn('action_sheet_generated');
                }
                if (Schema::connection('sqlsrv')->hasColumn('subapplications', 'action_sheet_generated_at')) {
                    $table->dropColumn('action_sheet_generated_at');
                }
                if (Schema::connection('sqlsrv')->hasColumn('subapplications', 'action_sheet_generated_by')) {
                    $table->dropColumn('action_sheet_generated_by');
                }
            });
        }
    }
};