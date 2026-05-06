<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('survey_report_requests', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('survey_report_requests', 'director_cadastral_signature_method')) {
                $table->string('director_cadastral_signature_method', 20)->nullable()->after('director_cadastral_approval_date');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('survey_report_requests', 'director_cadastral_signature')) {
                $table->string('director_cadastral_signature')->nullable()->after('director_cadastral_signature_method');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('survey_report_requests', 'director_cadastral_signature_verified_at')) {
                $table->dateTime('director_cadastral_signature_verified_at')->nullable()->after('director_cadastral_signature');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('survey_report_requests', 'director_cadastral_signature_approved_by')) {
                $table->unsignedBigInteger('director_cadastral_signature_approved_by')->nullable()->after('director_cadastral_signature_verified_at');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('survey_report_requests', function (Blueprint $table) {
            $table->dropColumn([
                'director_cadastral_signature_method',
                'director_cadastral_signature',
                'director_cadastral_signature_verified_at',
                'director_cadastral_signature_approved_by',
            ]);
        });
    }
};
