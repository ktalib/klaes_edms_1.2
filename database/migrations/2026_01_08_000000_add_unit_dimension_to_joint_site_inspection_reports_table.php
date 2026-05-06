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
            if (!Schema::connection('sqlsrv')->hasColumn('joint_site_inspection_reports', 'unit_dimension')) {
                $table->string('unit_dimension')->nullable()->after('sections_count');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('sqlsrv')->table('joint_site_inspection_reports', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('joint_site_inspection_reports', 'unit_dimension')) {
                $table->dropColumn('unit_dimension');
            }
        });
    }
};
