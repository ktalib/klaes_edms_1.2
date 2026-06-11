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
            if (!Schema::connection('sqlsrv')->hasColumn('phs_onboarding_requests', 'invoice_number')) {
                $table->string('invoice_number', 100)->nullable()->after('additional_notes');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('phs_onboarding_requests', 'invoice_generated_at')) {
                $table->dateTime('invoice_generated_at')->nullable()->after('invoice_number');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('phs_onboarding_requests', 'invoice_sent_at')) {
                $table->dateTime('invoice_sent_at')->nullable()->after('invoice_generated_at');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('phs_onboarding_requests', 'invoice_pdf_path')) {
                $table->string('invoice_pdf_path', 500)->nullable()->after('invoice_sent_at');
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
            foreach (['invoice_number', 'invoice_generated_at', 'invoice_sent_at', 'invoice_pdf_path'] as $col) {
                if (Schema::connection('sqlsrv')->hasColumn('phs_onboarding_requests', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
