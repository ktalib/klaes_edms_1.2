<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('consent_applications', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('consent_applications', 'right_of_occupancy_landuse')) {
                $table->string('right_of_occupancy_landuse', 120)->nullable()->after('right_of_occupancy_number');
            }

            if (!Schema::connection('sqlsrv')->hasColumn('consent_applications', 'date_of_grant')) {
                $table->date('date_of_grant')->nullable()->after('location_of_right_of_occupancy');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('consent_applications', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('consent_applications', 'right_of_occupancy_landuse')) {
                $table->dropColumn('right_of_occupancy_landuse');
            }

            if (Schema::connection('sqlsrv')->hasColumn('consent_applications', 'date_of_grant')) {
                $table->dropColumn('date_of_grant');
            }
        });
    }
};
