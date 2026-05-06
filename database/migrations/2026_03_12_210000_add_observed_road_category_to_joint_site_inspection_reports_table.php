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
            if (!Schema::connection('sqlsrv')->hasColumn('joint_site_inspection_reports', 'observed_road_category')) {
                $table->string('observed_road_category', 255)
                    ->nullable()
                    ->after('existing_road_reservation')
                    ->comment('Observed road category specified by user during JSI');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('sqlsrv')->table('joint_site_inspection_reports', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('joint_site_inspection_reports', 'observed_road_category')) {
                $table->dropColumn('observed_road_category');
            }
        });
    }
};
