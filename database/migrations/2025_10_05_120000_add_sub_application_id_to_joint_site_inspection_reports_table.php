<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('joint_site_inspection_reports', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('joint_site_inspection_reports', 'sub_application_id')) {
                $table->unsignedBigInteger('sub_application_id')->nullable()->after('application_id');
            }
        });

        DB::connection('sqlsrv')->statement("IF NOT EXISTS (SELECT name FROM sys.indexes WHERE name = 'joint_site_inspection_reports_app_sub_idx') CREATE INDEX joint_site_inspection_reports_app_sub_idx ON joint_site_inspection_reports (application_id, sub_application_id)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::connection('sqlsrv')->statement("IF EXISTS (SELECT name FROM sys.indexes WHERE name = 'joint_site_inspection_reports_app_sub_idx') DROP INDEX joint_site_inspection_reports_app_sub_idx ON joint_site_inspection_reports");

        Schema::connection('sqlsrv')->table('joint_site_inspection_reports', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('joint_site_inspection_reports', 'sub_application_id')) {
                $table->dropColumn('sub_application_id');
            }
        });
    }
};
