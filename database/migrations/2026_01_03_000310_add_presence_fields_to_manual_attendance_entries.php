<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('manual_attendance_entries', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('manual_attendance_entries', 'is_present')) {
                $table->boolean('is_present')->nullable()->after('attendance_date');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('manual_attendance_entries', 'check_in_time')) {
                $table->timestamp('check_in_time')->nullable()->after('is_present');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('manual_attendance_entries', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('manual_attendance_entries', 'check_in_time')) {
                $table->dropColumn('check_in_time');
            }
            if (Schema::connection('sqlsrv')->hasColumn('manual_attendance_entries', 'is_present')) {
                $table->dropColumn('is_present');
            }
        });
    }
};
