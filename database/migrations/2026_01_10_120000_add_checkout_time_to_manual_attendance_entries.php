<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('manual_attendance_entries', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('manual_attendance_entries', 'check_out_time')) {
                $table->timestamp('check_out_time')->nullable()->after('check_in_time');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('manual_attendance_entries', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('manual_attendance_entries', 'check_out_time')) {
                $table->dropColumn('check_out_time');
            }
        });
    }
};
