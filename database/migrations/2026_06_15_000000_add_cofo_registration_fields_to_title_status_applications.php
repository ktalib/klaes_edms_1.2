<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection('sqlsrv')->table('title_status_applications', function (Blueprint $table) {
            // Certificate of Occupancy No. being revoked (overrides the plot_no/file_no derived ref)
            if (!Schema::connection('sqlsrv')->hasColumn('title_status_applications', 'cofo_number')) {
                $table->string('cofo_number', 255)->nullable()->after('land_use');
            }
            // Land Registry registration particulars: "registered as No <title_no> at page <reg_page> in Volume <reg_volume>"
            if (!Schema::connection('sqlsrv')->hasColumn('title_status_applications', 'reg_page')) {
                $table->string('reg_page', 100)->nullable()->after('title_no');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('title_status_applications', 'reg_volume')) {
                $table->string('reg_volume', 100)->nullable()->after('reg_page');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('title_status_applications', function (Blueprint $table) {
            foreach (['reg_volume', 'reg_page', 'cofo_number'] as $col) {
                if (Schema::connection('sqlsrv')->hasColumn('title_status_applications', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
