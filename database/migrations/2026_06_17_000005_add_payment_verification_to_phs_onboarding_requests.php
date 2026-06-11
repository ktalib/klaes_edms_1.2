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
            if (!Schema::connection('sqlsrv')->hasColumn('phs_onboarding_requests', 'payment_status')) {
                // not_paid | incomplete | completed | overpaid
                $table->string('payment_status', 20)->default('not_paid')->after('payment_received_at');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('phs_onboarding_requests', 'expected_amount')) {
                $table->decimal('expected_amount', 18, 2)->nullable()->after('payment_status');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('phs_onboarding_requests', 'verified_amount')) {
                $table->decimal('verified_amount', 18, 2)->nullable()->after('expected_amount');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('phs_onboarding_requests', 'outstanding_amount')) {
                $table->decimal('outstanding_amount', 18, 2)->nullable()->after('verified_amount');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('phs_onboarding_requests', 'payment_verified_at')) {
                $table->dateTime('payment_verified_at')->nullable()->after('outstanding_amount');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('phs_onboarding_requests', 'payment_verified_by')) {
                $table->unsignedBigInteger('payment_verified_by')->nullable()->after('payment_verified_at');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('phs_onboarding_requests', 'payment_verification_notes')) {
                $table->string('payment_verification_notes', 1000)->nullable()->after('payment_verified_by');
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
            foreach ([
                'payment_status', 'expected_amount', 'verified_amount', 'outstanding_amount',
                'payment_verified_at', 'payment_verified_by', 'payment_verification_notes',
            ] as $col) {
                if (Schema::connection('sqlsrv')->hasColumn('phs_onboarding_requests', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
