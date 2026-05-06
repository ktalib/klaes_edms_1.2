<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('joint_site_inspection_reports', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('joint_site_inspection_reports', 'inspection_officer_id')) {
                $table->unsignedBigInteger('inspection_officer_id')->nullable()->after('inspection_officer')
                    ->comment('FK to pp_inspectors.id for selected inspection officer');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('joint_site_inspection_reports', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('joint_site_inspection_reports', 'inspection_officer_id')) {
                $table->dropColumn('inspection_officer_id');
            }
        });
    }
};
