<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Create the pp_betterment_rates configuration table and seed default rates.
     *
     * Formula: Total Fee = Base Fee + max(0, (Total Area − Base Area) × Rate Per Extra M²)
     */
    public function up(): void
    {
        Schema::connection('sqlsrv')->create('pp_betterment_rates', function (Blueprint $table) {
            $table->id();
            $table->string('category', 100)->unique()->comment('Land use category');
            $table->decimal('base_area', 10, 2)->comment('Base area in m²');
            $table->decimal('base_fee', 12, 2)->comment('Base fee in Naira');
            $table->decimal('rate_per_extra_sqm', 10, 2)->default(5.00)->comment('₦ per extra m² above base');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed default rate table
        $now = now();
        DB::connection('sqlsrv')->table('pp_betterment_rates')->insert([
            ['category' => 'Industrial', 'base_area' => 100, 'base_fee' => 35000, 'rate_per_extra_sqm' => 5, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['category' => 'Commercial', 'base_area' => 100, 'base_fee' => 25000, 'rate_per_extra_sqm' => 5, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['category' => 'Petrol Filling Station', 'base_area' => 100, 'base_fee' => 50000, 'rate_per_extra_sqm' => 5, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['category' => 'Agricultural', 'base_area' => 5000, 'base_fee' => 25000, 'rate_per_extra_sqm' => 5, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['category' => 'Residential', 'base_area' => 100, 'base_fee' => 15000, 'rate_per_extra_sqm' => 5, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('pp_betterment_rates');
    }
};
