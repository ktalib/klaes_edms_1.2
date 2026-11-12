<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Physical Planning staff confirmed the Commercial betterment base fee is ₦35,000 (was ₦25,000).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('pp_betterment_rates')) {
            return;
        }

        DB::connection('sqlsrv')->table('pp_betterment_rates')
            ->where('category', 'Commercial')
            ->update(['base_fee' => 35000, 'updated_at' => now()]);
    }

    public function down(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('pp_betterment_rates')) {
            return;
        }

        DB::connection('sqlsrv')->table('pp_betterment_rates')
            ->where('category', 'Commercial')
            ->update(['base_fee' => 25000, 'updated_at' => now()]);
    }
};
