<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add file_number column to joint_site_inspection_reports table.
     * This denormalized field stores the file number directly on the JSI record
     * for simpler querying, especially for Conversion Applications.
     */
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('joint_site_inspection_reports', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('joint_site_inspection_reports', 'file_number')) {
                $table->string('file_number', 100)->nullable()->after('application_id')
                    ->comment('Denormalized file number for direct querying');
                $table->index('file_number');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('sqlsrv')->table('joint_site_inspection_reports', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('joint_site_inspection_reports', 'file_number')) {
                $table->dropIndex(['file_number']);
                $table->dropColumn('file_number');
            }
        });
    }
};
