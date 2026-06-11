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
            if (!Schema::connection('sqlsrv')->hasColumn('phs_token_transactions', 'topup_bundle_count')) {
                $table->integer('topup_bundle_count')->nullable()->after('package_name');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('phs_token_transactions', 'topup_unit_price')) {
                $table->decimal('topup_unit_price', 18, 2)->nullable()->after('topup_bundle_count');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('phs_token_transactions', 'payment_gateway_reference')) {
                $table->string('payment_gateway_reference', 255)->nullable()->after('topup_unit_price');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('phs_token_transactions', 'payment_confirmed_at')) {
                $table->dateTime('payment_confirmed_at')->nullable()->after('payment_gateway_reference');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('phs_token_transactions', 'credited_at')) {
                $table->dateTime('credited_at')->nullable()->after('payment_confirmed_at');
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
            foreach (['topup_bundle_count', 'topup_unit_price', 'payment_gateway_reference', 'payment_confirmed_at', 'credited_at'] as $col) {
                if (Schema::connection('sqlsrv')->hasColumn('phs_token_transactions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
