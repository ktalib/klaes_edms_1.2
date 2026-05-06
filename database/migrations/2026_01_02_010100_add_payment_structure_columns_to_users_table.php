<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('users', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('users', 'workstation_payment_structure_id')) {
                $table->unsignedBigInteger('workstation_payment_structure_id')->nullable()->after('work_station');
            }

            if (!Schema::connection('sqlsrv')->hasColumn('users', 'base_salary_override')) {
                $table->decimal('base_salary_override', 12, 2)->nullable()->after('workstation_payment_structure_id');
            }

            if (!Schema::connection('sqlsrv')->hasColumn('users', 'base_salary_source')) {
                $table->string('base_salary_source')->nullable()->after('base_salary_override');
            }

            $table->index('workstation_payment_structure_id', 'users_workstation_structure_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('users', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('users', 'base_salary_source')) {
                $table->dropColumn('base_salary_source');
            }
            if (Schema::connection('sqlsrv')->hasColumn('users', 'base_salary_override')) {
                $table->dropColumn('base_salary_override');
            }
            if (Schema::connection('sqlsrv')->hasColumn('users', 'workstation_payment_structure_id')) {
                $table->dropColumn('workstation_payment_structure_id');
            }
        });
    }
};
