<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection('sqlsrv')->table('users', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('users', 'shift_code')) {
                $table->string('shift_code', 32)->default('full_day')->after('man_hours_per_day');
            }

            if (!Schema::connection('sqlsrv')->hasColumn('users', 'auto_deactivate')) {
                $table->boolean('auto_deactivate')->default(true)->after('shift_code');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('users', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('users', 'auto_deactivate')) {
                $table->dropColumn('auto_deactivate');
            }

            if (Schema::connection('sqlsrv')->hasColumn('users', 'shift_code')) {
                $table->dropColumn('shift_code');
            }
        });
    }
};
