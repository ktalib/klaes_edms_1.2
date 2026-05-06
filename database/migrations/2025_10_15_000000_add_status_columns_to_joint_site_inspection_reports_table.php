<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('joint_site_inspection_reports', function (Blueprint $table) {
            // Add status tracking columns (is_generated and is_submitted already exist)
            if (!Schema::connection('sqlsrv')->hasColumn('joint_site_inspection_reports', 'generated_at')) {
                $table->timestamp('generated_at')->nullable();
            }
            if (!Schema::connection('sqlsrv')->hasColumn('joint_site_inspection_reports', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable();
            }
            if (!Schema::connection('sqlsrv')->hasColumn('joint_site_inspection_reports', 'generated_by')) {
                $table->string('generated_by')->nullable();
            }
            if (!Schema::connection('sqlsrv')->hasColumn('joint_site_inspection_reports', 'submitted_by')) {
                $table->string('submitted_by')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('sqlsrv')->table('joint_site_inspection_reports', function (Blueprint $table) {
            // Only drop the columns we might have added
            if (Schema::connection('sqlsrv')->hasColumn('joint_site_inspection_reports', 'generated_at')) {
                $table->dropColumn('generated_at');
            }
            if (Schema::connection('sqlsrv')->hasColumn('joint_site_inspection_reports', 'submitted_at')) {
                $table->dropColumn('submitted_at');
            }
            if (Schema::connection('sqlsrv')->hasColumn('joint_site_inspection_reports', 'generated_by')) {
                $table->dropColumn('generated_by');
            }
            if (Schema::connection('sqlsrv')->hasColumn('joint_site_inspection_reports', 'submitted_by')) {
                $table->dropColumn('submitted_by');
            }
        });
    }
};