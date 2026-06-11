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
        Schema::connection('sqlsrv')->table('phs_token_transactions', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('phs_token_transactions', 'expected_amount')) {
                $table->decimal('expected_amount', 18, 2)->nullable()->after('amount');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('phs_token_transactions', 'paid_amount')) {
                $table->decimal('paid_amount', 18, 2)->nullable()->after('expected_amount');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('phs_token_transactions', 'payment_variance')) {
                $table->decimal('payment_variance', 18, 2)->nullable()->after('paid_amount');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('phs_token_transactions', 'payment_proof_path')) {
                $table->string('payment_proof_path', 500)->nullable()->after('payment_variance');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('phs_token_transactions', 'bank_reference')) {
                $table->string('bank_reference', 255)->nullable()->after('payment_proof_path');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('phs_token_transactions', 'payment_date')) {
                $table->date('payment_date')->nullable()->after('bank_reference');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('phs_token_transactions', 'validation_status')) {
                // pending | verified | incomplete | overpaid
                $table->string('validation_status', 20)->nullable()->default('pending')->after('payment_date');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('phs_token_transactions', 'validation_notes')) {
                $table->string('validation_notes', 1000)->nullable()->after('validation_status');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('phs_token_transactions', 'validated_by')) {
                $table->unsignedBigInteger('validated_by')->nullable()->after('validation_notes');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('phs_token_transactions', 'validated_at')) {
                $table->dateTime('validated_at')->nullable()->after('validated_by');
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
        Schema::connection('sqlsrv')->table('phs_token_transactions', function (Blueprint $table) {
            foreach ([
                'expected_amount', 'paid_amount', 'payment_variance', 'payment_proof_path',
                'bank_reference', 'payment_date', 'validation_status', 'validation_notes',
                'validated_by', 'validated_at',
            ] as $col) {
                if (Schema::connection('sqlsrv')->hasColumn('phs_token_transactions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
