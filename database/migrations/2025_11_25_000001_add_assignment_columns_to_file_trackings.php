<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('file_trackings', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('file_trackings', 'origin_registry')) {
                $table->string('origin_registry', 150)->nullable()->after('current_location');
            }

            if (!Schema::connection('sqlsrv')->hasColumn('file_trackings', 'assigned_office')) {
                $table->unsignedBigInteger('assigned_office')->nullable()->after('origin_registry');
            }

            if (!Schema::connection('sqlsrv')->hasColumn('file_trackings', 'assigned_to')) {
                $table->unsignedBigInteger('assigned_to')->nullable()->after('assigned_office');
            }

            if (!Schema::connection('sqlsrv')->hasColumn('file_trackings', 'assigned_by')) {
                $table->unsignedBigInteger('assigned_by')->nullable()->after('assigned_to');
            }

            if (!Schema::connection('sqlsrv')->hasColumn('file_trackings', 'assigned_at')) {
                $table->dateTime('assigned_at')->nullable()->after('assigned_by');
            }

            if (!Schema::connection('sqlsrv')->hasColumn('file_trackings', 'accepted_at')) {
                $table->dateTime('accepted_at')->nullable()->after('assigned_at');
            }

            if (!Schema::connection('sqlsrv')->hasColumn('file_trackings', 'rejected_at')) {
                $table->dateTime('rejected_at')->nullable()->after('accepted_at');
            }

            if (!Schema::connection('sqlsrv')->hasColumn('file_trackings', 'reject_note')) {
                $table->text('reject_note')->nullable()->after('rejected_at');
            }

            if (!Schema::connection('sqlsrv')->hasColumn('file_trackings', 'assignment_status')) {
                $table->string('assignment_status', 30)->default('pending')->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('file_trackings', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('file_trackings', 'assignment_status')) {
                $table->dropColumn('assignment_status');
            }

            if (Schema::connection('sqlsrv')->hasColumn('file_trackings', 'reject_note')) {
                $table->dropColumn('reject_note');
            }

            if (Schema::connection('sqlsrv')->hasColumn('file_trackings', 'rejected_at')) {
                $table->dropColumn('rejected_at');
            }

            if (Schema::connection('sqlsrv')->hasColumn('file_trackings', 'accepted_at')) {
                $table->dropColumn('accepted_at');
            }

            if (Schema::connection('sqlsrv')->hasColumn('file_trackings', 'assigned_at')) {
                $table->dropColumn('assigned_at');
            }

            if (Schema::connection('sqlsrv')->hasColumn('file_trackings', 'assigned_by')) {
                $table->dropColumn('assigned_by');
            }

            if (Schema::connection('sqlsrv')->hasColumn('file_trackings', 'assigned_to')) {
                $table->dropColumn('assigned_to');
            }

            if (Schema::connection('sqlsrv')->hasColumn('file_trackings', 'assigned_office')) {
                $table->dropColumn('assigned_office');
            }

            if (Schema::connection('sqlsrv')->hasColumn('file_trackings', 'origin_registry')) {
                $table->dropColumn('origin_registry');
            }
        });
    }
};
