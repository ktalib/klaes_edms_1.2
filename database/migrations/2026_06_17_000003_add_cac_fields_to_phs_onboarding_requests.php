<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('sqlsrv')->table('phs_onboarding_requests', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('phs_onboarding_requests', 'cac_registration_number')) {
                $table->string('cac_registration_number', 100)->nullable()->after('additional_notes');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('phs_onboarding_requests', 'cac_document_path')) {
                $table->string('cac_document_path', 500)->nullable()->after('cac_registration_number');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('phs_onboarding_requests', 'additional_documents')) {
                $table->text('additional_documents')->nullable()->after('cac_document_path'); // JSON array of paths
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->table('phs_onboarding_requests', function (Blueprint $table) {
            foreach (['cac_registration_number', 'cac_document_path', 'additional_documents'] as $col) {
                if (Schema::connection('sqlsrv')->hasColumn('phs_onboarding_requests', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
