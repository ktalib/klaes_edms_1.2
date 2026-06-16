<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('phs_onboarding_requests', function (Blueprint $table) {
            $table->string('payment_token', 64)->nullable()->after('lsa_signed_at');
            $table->timestamp('legal_approved_at')->nullable()->after('payment_token');
            $table->string('legal_approved_by', 150)->nullable()->after('legal_approved_at');
            $table->text('legal_rejection_reason')->nullable()->after('legal_approved_by');
            $table->timestamp('legal_sla_approved_at')->nullable()->after('legal_rejection_reason');
            $table->string('legal_sla_approved_by', 150)->nullable()->after('legal_sla_approved_at');
            $table->string('paystack_reference', 100)->nullable()->after('legal_sla_approved_by');
            $table->decimal('paystack_amount', 12, 2)->nullable()->after('paystack_reference');
        });
    }

    public function down(): void
    {
        Schema::table('phs_onboarding_requests', function (Blueprint $table) {
            $table->dropColumn([
                'payment_token', 'legal_approved_at', 'legal_approved_by',
                'legal_rejection_reason', 'legal_sla_approved_at', 'legal_sla_approved_by',
                'paystack_reference', 'paystack_amount',
            ]);
        });
    }
};
