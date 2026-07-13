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
            if (!Schema::connection('sqlsrv')->hasColumn('users', 'is_on_leave')) {
                $table->boolean('is_on_leave')->default(false)->after('staff_type_category');
            }

            if (!Schema::connection('sqlsrv')->hasColumn('users', 'leave_start_date')) {
                $table->date('leave_start_date')->nullable()->after('is_on_leave');
            }

            if (!Schema::connection('sqlsrv')->hasColumn('users', 'leave_end_date')) {
                $table->date('leave_end_date')->nullable()->after('leave_start_date');
            }

            if (!Schema::connection('sqlsrv')->hasColumn('users', 'leave_reason')) {
                $table->string('leave_reason', 255)->nullable()->after('leave_end_date');
            }

            if (!Schema::connection('sqlsrv')->hasColumn('users', 'deputy_user_id')) {
                $table->unsignedBigInteger('deputy_user_id')->nullable()->after('leave_reason');
            }

            if (!Schema::connection('sqlsrv')->hasColumn('users', 'deputy_redirect_notes')) {
                $table->string('deputy_redirect_notes', 1000)->nullable()->after('deputy_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('users', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('users', 'deputy_redirect_notes')) {
                $table->dropColumn('deputy_redirect_notes');
            }

            if (Schema::connection('sqlsrv')->hasColumn('users', 'deputy_user_id')) {
                $table->dropColumn('deputy_user_id');
            }

            if (Schema::connection('sqlsrv')->hasColumn('users', 'leave_reason')) {
                $table->dropColumn('leave_reason');
            }

            if (Schema::connection('sqlsrv')->hasColumn('users', 'leave_end_date')) {
                $table->dropColumn('leave_end_date');
            }

            if (Schema::connection('sqlsrv')->hasColumn('users', 'leave_start_date')) {
                $table->dropColumn('leave_start_date');
            }

            if (Schema::connection('sqlsrv')->hasColumn('users', 'is_on_leave')) {
                $table->dropColumn('is_on_leave');
            }
        });
    }
};
