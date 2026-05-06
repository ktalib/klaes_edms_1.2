<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $connection = Schema::connection('sqlsrv');

        if ($connection->hasTable('bill_balances') && !$connection->hasTable('deeds_bill_balances_metadata')) {
            Schema::connection('sqlsrv')->rename('bill_balances', 'deeds_bill_balances_metadata');
        }

        if (!$connection->hasTable('deeds_bill_balances_metadata')) {
            return;
        }

        Schema::connection('sqlsrv')->table('deeds_bill_balances_metadata', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('deeds_bill_balances_metadata', 'applicant_address')) {
                $table->string('applicant_address', 255)->nullable();
            }

            if (!Schema::connection('sqlsrv')->hasColumn('deeds_bill_balances_metadata', 'location_station')) {
                $table->string('location_station', 120)->nullable();
            }

            if (!Schema::connection('sqlsrv')->hasColumn('deeds_bill_balances_metadata', 'district')) {
                $table->string('district', 120)->nullable();
            }

            if (!Schema::connection('sqlsrv')->hasColumn('deeds_bill_balances_metadata', 'billing_id')) {
                $table->bigInteger('billing_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        $connection = Schema::connection('sqlsrv');

        if (!$connection->hasTable('deeds_bill_balances_metadata')) {
            return;
        }

        Schema::connection('sqlsrv')->table('deeds_bill_balances_metadata', function (Blueprint $table) {
            $columns = ['applicant_address', 'location_station', 'district', 'billing_id'];

            foreach ($columns as $column) {
                if (Schema::connection('sqlsrv')->hasColumn('deeds_bill_balances_metadata', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        if ($connection->hasTable('deeds_bill_balances_metadata')) {
            Schema::connection('sqlsrv')->rename('deeds_bill_balances_metadata', 'bill_balances');
        }
    }
};
