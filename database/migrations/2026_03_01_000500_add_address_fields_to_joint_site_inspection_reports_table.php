<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('joint_site_inspection_reports', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('joint_site_inspection_reports', 'applicant_address')) {
                $table->string('applicant_address', 500)->nullable()->after('applicant_name');
            }

            if (!Schema::connection('sqlsrv')->hasColumn('joint_site_inspection_reports', 'applicant_address_components')) {
                $table->json('applicant_address_components')->nullable()->after('applicant_address');
            }

            if (!Schema::connection('sqlsrv')->hasColumn('joint_site_inspection_reports', 'location_components')) {
                $table->json('location_components')->nullable()->after('location');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('joint_site_inspection_reports', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('joint_site_inspection_reports', 'applicant_address_components')) {
                $table->dropColumn('applicant_address_components');
            }

            if (Schema::connection('sqlsrv')->hasColumn('joint_site_inspection_reports', 'location_components')) {
                $table->dropColumn('location_components');
            }

            if (Schema::connection('sqlsrv')->hasColumn('joint_site_inspection_reports', 'applicant_address')) {
                $table->dropColumn('applicant_address');
            }
        });
    }
};
