<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('users', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('users', 'work_station')) {
                $table->string('work_station', 150)->nullable()->after('department_id');
            }
        });

        if (Schema::connection('sqlsrv')->hasColumn('users', 'registry')) {
            DB::connection('sqlsrv')->statement('UPDATE users SET work_station = registry WHERE work_station IS NULL AND registry IS NOT NULL');

            Schema::connection('sqlsrv')->table('users', function (Blueprint $table) {
                $table->dropColumn('registry');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('users', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('users', 'registry')) {
                $table->string('registry', 150)->nullable()->after('department_id');
            }
        });

        if (Schema::connection('sqlsrv')->hasColumn('users', 'work_station')) {
            DB::connection('sqlsrv')->statement('UPDATE users SET registry = work_station WHERE registry IS NULL AND work_station IS NOT NULL');

            Schema::connection('sqlsrv')->table('users', function (Blueprint $table) {
                $table->dropColumn('work_station');
            });
        }
    }
};
