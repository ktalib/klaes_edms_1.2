<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('users', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('users', 'work_days_per_week')) {
                $table->unsignedTinyInteger('work_days_per_week')->nullable()->after('user_level');
            }

            if (!Schema::connection('sqlsrv')->hasColumn('users', 'man_hours_per_day')) {
                $table->unsignedTinyInteger('man_hours_per_day')->nullable()->after('work_days_per_week');
            }

            if (!Schema::connection('sqlsrv')->hasColumn('users', 'staff_type_category')) {
                $table->string('staff_type_category', 20)->nullable()->after('man_hours_per_day');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('users', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('users', 'staff_type_category')) {
                $table->dropColumn('staff_type_category');
            }

            if (Schema::connection('sqlsrv')->hasColumn('users', 'man_hours_per_day')) {
                $table->dropColumn('man_hours_per_day');
            }

            if (Schema::connection('sqlsrv')->hasColumn('users', 'work_days_per_week')) {
                $table->dropColumn('work_days_per_week');
            }
        });
    }
};
