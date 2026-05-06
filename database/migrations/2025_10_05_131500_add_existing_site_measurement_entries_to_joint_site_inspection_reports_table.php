<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('joint_site_inspection_reports', function (Blueprint $table) {
            $table->text('existing_site_measurement_entries')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('joint_site_inspection_reports', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('joint_site_inspection_reports', 'existing_site_measurement_entries')) {
                $table->dropColumn('existing_site_measurement_entries');
            }
        });
    }
};
