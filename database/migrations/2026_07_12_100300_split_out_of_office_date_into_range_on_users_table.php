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
            if (!Schema::connection('sqlsrv')->hasColumn('users', 'out_of_office_from')) {
                $table->date('out_of_office_from')->nullable()->after('deputy_user_id');
            }

            if (!Schema::connection('sqlsrv')->hasColumn('users', 'out_of_office_to')) {
                $table->date('out_of_office_to')->nullable()->after('out_of_office_from');
            }

            if (Schema::connection('sqlsrv')->hasColumn('users', 'out_of_office_date')) {
                $table->dropColumn('out_of_office_date');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('users', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('users', 'out_of_office_date')) {
                $table->date('out_of_office_date')->nullable()->after('deputy_user_id');
            }

            if (Schema::connection('sqlsrv')->hasColumn('users', 'out_of_office_to')) {
                $table->dropColumn('out_of_office_to');
            }

            if (Schema::connection('sqlsrv')->hasColumn('users', 'out_of_office_from')) {
                $table->dropColumn('out_of_office_from');
            }
        });
    }
};
