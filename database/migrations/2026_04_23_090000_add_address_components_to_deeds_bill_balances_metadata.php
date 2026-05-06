<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('deeds_bill_balances_metadata')) {
            return;
        }

        Schema::connection('sqlsrv')->table('deeds_bill_balances_metadata', function (Blueprint $table) {
            $columns = [
                'app_plot_number'  => ['string', 50],
                'app_street_name'  => ['string', 120],
                'app_district'     => ['string', 120],
                'app_lga'          => ['string', 120],
                'app_state'        => ['string', 120],
                'loc_plot_number'  => ['string', 50],
                'loc_street_name'  => ['string', 120],
                'loc_district'     => ['string', 120],
                'loc_lga'          => ['string', 120],
                'loc_state'        => ['string', 120],
            ];

            foreach ($columns as $name => [, $length]) {
                if (!Schema::connection('sqlsrv')->hasColumn('deeds_bill_balances_metadata', $name)) {
                    $table->string($name, $length)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('deeds_bill_balances_metadata')) {
            return;
        }

        Schema::connection('sqlsrv')->table('deeds_bill_balances_metadata', function (Blueprint $table) {
            $columns = [
                'app_plot_number', 'app_street_name', 'app_district', 'app_lga', 'app_state',
                'loc_plot_number', 'loc_street_name', 'loc_district', 'loc_lga', 'loc_state',
            ];
            foreach ($columns as $name) {
                if (Schema::connection('sqlsrv')->hasColumn('deeds_bill_balances_metadata', $name)) {
                    $table->dropColumn($name);
                }
            }
        });
    }
};
