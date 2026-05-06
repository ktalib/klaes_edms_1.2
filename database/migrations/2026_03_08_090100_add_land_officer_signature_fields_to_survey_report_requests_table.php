<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('survey_report_requests', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('survey_report_requests', 'land_officer_id')) {
                $table->unsignedBigInteger('land_officer_id')->nullable()->after('lands_section_name');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('survey_report_requests', 'signature_verification_method')) {
                $table->string('signature_verification_method', 20)->nullable()->after('land_officer_id');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('survey_report_requests', 'signature_verified_at')) {
                $table->dateTime('signature_verified_at')->nullable()->after('signature_verification_method');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('survey_report_requests', 'signature_approved_by')) {
                $table->unsignedBigInteger('signature_approved_by')->nullable()->after('signature_verified_at');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('survey_report_requests', function (Blueprint $table) {
            foreach ([
                'land_officer_id',
                'signature_verification_method',
                'signature_verified_at',
                'signature_approved_by',
            ] as $column) {
                if (Schema::connection('sqlsrv')->hasColumn('survey_report_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
